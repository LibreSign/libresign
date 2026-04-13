<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\DocMdp\FilePolicy;

use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\DocMdp\FilePolicy\DocMdpFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\DocMdp\DocMdpPolicy;
use PHPUnit\Framework\MockObject\MockObject;

final class DocMdpFilePolicyApplierTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private PolicyService&MockObject $policyService;
	private FileService&MockObject $fileService;

	public function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->fileService = $this->createMock(FileService::class);
	}

	private function getApplier(): DocMdpFilePolicyApplier {
		return new DocMdpFilePolicyApplier(
			$this->policyService,
			$this->fileService,
		);
	}

	public function testApplySetsDocMdpLevelAndStoresSnapshot(): void {
		$file = new \OCA\Libresign\Db\File();

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(
				DocMdpPolicy::KEY,
				null,
				[DocMdpPolicy::KEY => 3],
				['type' => 'user', 'id' => 'john'],
			)
			->willReturn($this->createResolvedPolicy(3, sourceScope: 'user'));

		$this->getApplier()->apply($file, [
			'policyOverrides' => [DocMdpPolicy::KEY => 3],
			'policyActiveContext' => ['type' => 'user', 'id' => 'john'],
		]);

		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS, $file->getDocmdpLevelEnum());
		$this->assertSame([
			'policy_snapshot' => [
				'docmdp' => [
					'effectiveValue' => 3,
					'sourceScope' => 'user',
				],
			],
		], $file->getMetadata());
	}

	public function testSyncUpdatesWhenResolvedValueChanges(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setDocmdpLevelEnum(DocMdpLevel::NOT_CERTIFIED);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(DocMdpPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy(2));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		$this->getApplier()->sync($file, []);
		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING, $file->getDocmdpLevelEnum());
	}

	public function testSyncDoesNotPersistWhenNothingChangedAndSnapshotExists(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$file->setDocmdpLevelEnum(DocMdpLevel::CERTIFIED_FORM_FILLING);
		$file->setMetadata([
			'policy_snapshot' => [
				'docmdp' => [
					'effectiveValue' => 2,
					'sourceScope' => 'system',
				],
			],
		]);

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(DocMdpPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy(2));

		$this->fileService
			->expects($this->never())
			->method('update');

		$this->getApplier()->sync($file, []);
	}

	private function createResolvedPolicy(int $effectiveValue, string $sourceScope = 'system'): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey(DocMdpPolicy::KEY)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope)
			->setCanUseAsRequestOverride(true)
			->setBlockedBy(null);
	}
}
