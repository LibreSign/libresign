<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\IdentificationDocuments\FilePolicy;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\FilePolicy\IdentificationDocumentsFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicy;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;

final class IdentificationDocumentsFilePolicyApplierTest extends \OCA\Libresign\Tests\Unit\TestCase {
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

	private function getApplier(): IdentificationDocumentsFilePolicyApplier {
		return new IdentificationDocumentsFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
		);
	}

	public function testApplyStoresSnapshot(): void {
		$file = new \OCA\Libresign\Db\File();
		$policyValue = [
			'enabled' => true,
			'approvers' => ['legal', 'admin'],
		];

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(
				IdentificationDocumentsPolicy::KEY,
				null,
				[IdentificationDocumentsPolicy::KEY => $policyValue],
				['type' => 'group', 'id' => 'g1'],
			)
			->willReturn($this->createResolvedPolicy($policyValue, sourceScope: 'group'));

		$this->getApplier()->apply($file, [
			'policyOverrides' => [IdentificationDocumentsPolicy::KEY => $policyValue],
			'policyActiveContext' => ['type' => 'group', 'id' => 'g1'],
		]);

		$this->assertSame([
			'policy_snapshot' => [
				IdentificationDocumentsPolicy::KEY => [
					'effectiveValue' => $policyValue,
					'sourceScope' => 'group',
				],
			],
		], $file->getMetadata());
	}

	public function testApplyThrowsWhenRequestOverrideIsBlocked(): void {
		$file = new \OCA\Libresign\Db\File();
		$policyValue = [
			'enabled' => true,
			'approvers' => ['legal'],
		];

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(IdentificationDocumentsPolicy::KEY, null, [IdentificationDocumentsPolicy::KEY => $policyValue])
			->willReturn($this->createResolvedPolicy(
				$policyValue,
				sourceScope: 'group',
				canUseAsRequestOverride: false,
				blockedBy: 'group',
			));

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);

		$this->getApplier()->apply($file, [
			'policyOverrides' => [IdentificationDocumentsPolicy::KEY => $policyValue],
		]);
	}

	public function testSyncUpdatesFileWhenSnapshotChanges(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$policyValue = [
			'enabled' => false,
			'approvers' => ['admin'],
		];

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(IdentificationDocumentsPolicy::KEY, 'john', [])
			->willReturn($this->createResolvedPolicy($policyValue, sourceScope: 'request'));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		$this->getApplier()->sync($file, []);
	}

	private function createResolvedPolicy(
		array $effectiveValue,
		string $sourceScope = 'system',
		bool $canUseAsRequestOverride = true,
		?string $blockedBy = null,
	): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey(IdentificationDocumentsPolicy::KEY)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope)
			->setCanUseAsRequestOverride($canUseAsRequestOverride)
			->setBlockedBy($blockedBy);
	}
}
