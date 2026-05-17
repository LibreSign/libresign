<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Signature\FilePolicy;

use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Signature\FilePolicy\SignatureFlowFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;

final class SignatureFlowFilePolicyApplierTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private PolicyService&MockObject $policyService;
	private FileService&MockObject $fileService;
	private IL10N&MockObject $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	private function getApplier(): SignatureFlowFilePolicyApplier {
		return new SignatureFlowFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
		);
	}

	public function testApplySetsFlowAndStoresSnapshot(): void {
		$file = new \OCA\Libresign\Db\File();

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(
				SignatureFlowPolicy::KEY,
				null,
				[SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value],
				['type' => 'group', 'id' => 'g1'],
			)
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::PARALLEL->value,
				sourceScope: 'group',
			));

		$this->getApplier()->apply($file, [
			'policyOverrides' => [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value],
			'policyActiveContext' => ['type' => 'group', 'id' => 'g1'],
		]);

		$this->assertSame(SignatureFlow::PARALLEL, $file->getSignatureFlowEnum());
		$this->assertSame([
			'policy_snapshot' => [
				'signature_flow' => [
					'effectiveValue' => SignatureFlow::PARALLEL->value,
					'sourceScope' => 'group',
				],
			],
		], $file->getMetadata());
	}

	public function testApplyThrowsWhenRequestOverrideIsBlocked(): void {
		$file = new \OCA\Libresign\Db\File();

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignatureFlowPolicy::KEY, null, [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value])
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::ORDERED_NUMERIC->value,
				sourceScope: 'group',
				canUseAsRequestOverride: false,
				blockedBy: 'group',
			));

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);

		$this->getApplier()->apply($file, [
			'policyOverrides' => [SignatureFlowPolicy::KEY => SignatureFlow::PARALLEL->value],
		]);
	}

	public function testSyncUpdatesFileWhenSnapshotChanges(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setSignatureFlowEnum(SignatureFlow::PARALLEL);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(SignatureFlowPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy(
				SignatureFlow::PARALLEL->value,
				sourceScope: 'group',
			));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		$this->getApplier()->sync($file, []);
	}

	private function createResolvedPolicy(
		string $effectiveValue,
		string $sourceScope = 'system',
		bool $canUseAsRequestOverride = true,
		?string $blockedBy = null,
	): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey(SignatureFlowPolicy::KEY)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope)
			->setCanUseAsRequestOverride($canUseAsRequestOverride)
			->setBlockedBy($blockedBy);
	}
}
