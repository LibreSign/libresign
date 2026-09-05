<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignatureRejection\FilePolicy;

use OCA\Libresign\Db\File;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\FilePolicy\SignatureRejectionFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureRejection\SignatureRejectionPolicyValue;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignatureRejectionFilePolicyApplierTest extends TestCase {
	private PolicyService&MockObject $policyService;
	private FileService&MockObject $fileService;
	private IL10N&MockObject $l10n;

	protected function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	private function getApplier(): SignatureRejectionFilePolicyApplier {
		return new SignatureRejectionFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
		);
	}

	private function createResolvedPolicy(mixed $effectiveValue, string $sourceScope = 'system'): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey(SignatureRejectionPolicy::KEY)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope);
	}

	public function testApplyFreezesTheNormalizedPolicyOnTheFile(): void {
		$file = new File();

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignatureRejectionPolicy::KEY, null)
			->willReturn($this->createResolvedPolicy(['enabled' => true, 'comment_mode' => 'required']));

		$this->getApplier()->apply($file, []);

		$this->assertSame([
			'policy_snapshot' => [
				SignatureRejectionPolicy::KEY => [
					'effectiveValue' => SignatureRejectionPolicyValue::normalize([
						'enabled' => true,
						'comment_mode' => 'required',
					]),
					'sourceScope' => 'system',
				],
			],
		], $file->getMetadata());
	}

	public function testApplyUsesTheActivePolicyContextWhenGiven(): void {
		$file = new File();

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignatureRejectionPolicy::KEY, null, [], ['type' => 'group', 'id' => 'legal'])
			->willReturn($this->createResolvedPolicy(['enabled' => true], 'group'));

		$this->getApplier()->apply($file, [
			'policyActiveContext' => ['type' => 'group', 'id' => 'legal'],
		]);

		$this->assertSame(
			'group',
			$file->getMetadata()['policy_snapshot'][SignatureRejectionPolicy::KEY]['sourceScope'],
		);
	}

	public function testSyncPersistsOnlyWhenTheSnapshotChanges(): void {
		$file = new File();
		$file->setUserId('requester');

		$this->policyService
			->method('resolveForUserId')
			->with(SignatureRejectionPolicy::KEY, 'requester')
			->willReturn($this->createResolvedPolicy(['enabled' => true]));

		$this->fileService->expects($this->once())->method('update')->with($file);

		$this->getApplier()->sync($file, []);
		// Second run resolves the same value, so there is nothing to persist.
		$this->getApplier()->sync($file, []);
	}

	public function testApplierParticipatesInCoreFlowSync(): void {
		$this->assertTrue($this->getApplier()->supportsCoreFlowSync());
	}
}
