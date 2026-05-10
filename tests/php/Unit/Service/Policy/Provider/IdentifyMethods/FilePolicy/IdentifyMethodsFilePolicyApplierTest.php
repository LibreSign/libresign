<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\IdentifyMethods\FilePolicy;

use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\FilePolicy\IdentifyMethodsFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicy;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;

final class IdentifyMethodsFilePolicyApplierTest extends \OCA\Libresign\Tests\Unit\TestCase {
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

	private function getApplier(): IdentifyMethodsFilePolicyApplier {
		return new IdentifyMethodsFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
		);
	}

	public function testApplyStoresIdentifyMethodsSnapshot(): void {
		$file = new \OCA\Libresign\Db\File();
		$effectiveValue = [
			[
				'name' => 'email',
				'enabled' => true,
				'requirement' => 'required',
				'mandatory' => true,
				'minimumTotalVerifiedFactors' => 2,
			],
		];

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(IdentifyMethodsPolicy::KEY, null, [])
			->willReturn($this->createResolvedPolicy($effectiveValue, 'group'));

		$this->getApplier()->apply($file, []);

		$this->assertSame([
			'policy_snapshot' => [
				IdentifyMethodsPolicy::KEY => [
					'effectiveValue' => $effectiveValue,
					'sourceScope' => 'group',
				],
			],
		], $file->getMetadata());
	}

	public function testSyncPersistsWhenSnapshotChanges(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');

		$effectiveValue = [
			[
				'name' => 'sms',
				'enabled' => true,
				'requirement' => 'optional',
				'mandatory' => false,
				'minimumTotalVerifiedFactors' => 2,
			],
		];

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(IdentifyMethodsPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy($effectiveValue, 'system'));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		$this->getApplier()->sync($file, []);
	}

	private function createResolvedPolicy(array $effectiveValue, string $sourceScope): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey(IdentifyMethodsPolicy::KEY)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope);
	}
}
