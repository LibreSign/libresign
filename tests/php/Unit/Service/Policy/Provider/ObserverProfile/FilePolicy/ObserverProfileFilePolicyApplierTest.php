<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\ObserverProfile\FilePolicy;

use OCA\Libresign\Db\File;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\ObserverProfile\FilePolicy\ObserverProfileFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicy;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ObserverProfileFilePolicyApplierTest extends TestCase {
	private PolicyService&MockObject $policyService;
	private FileService&MockObject $fileService;
	private IL10N&MockObject $l10n;

	protected function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->l10n = $this->createMock(IL10N::class);
	}

	public function testApplyStoresEffectivePolicySnapshot(): void {
		$file = new File();
		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(ObserverProfilePolicy::KEY, null, [])
			->willReturn(
				(new ResolvedPolicy())
					->setPolicyKey(ObserverProfilePolicy::KEY)
					->setEffectiveValue(true)
					->setSourceScope('system'),
			);

		$this->getApplier()->apply($file, []);

		$this->assertSame([
			'policy_snapshot' => [
				ObserverProfilePolicy::KEY => [
					'effectiveValue' => true,
					'sourceScope' => 'system',
				],
			],
		], $file->getMetadata());
	}

	public function testSyncPreservesStoredSnapshot(): void {
		$file = new File();
		$metadata = [
			'policy_snapshot' => [
				ObserverProfilePolicy::KEY => [
					'effectiveValue' => true,
					'sourceScope' => 'system',
				],
			],
		];
		$file->setMetadata($metadata);
		$this->policyService->expects($this->never())->method('resolveForUserId');
		$this->fileService->expects($this->never())->method('update');

		$this->getApplier()->sync($file, []);

		$this->assertSame($metadata, $file->getMetadata());
	}

	private function getApplier(): ObserverProfileFilePolicyApplier {
		return new ObserverProfileFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
		);
	}
}
