<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\LegalInformation\FilePolicy;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\LegalInformation\FilePolicy\LegalInformationFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\LegalInformation\LegalInformationPolicy;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class LegalInformationFilePolicyApplierTest extends TestCase {
	private PolicyService&MockObject $policyService;
	private FileService&MockObject $fileService;
	private IL10N&MockObject $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->fileService = $this->createMock(FileService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(static function (string $text, array $parameters = []): string {
			return $parameters === [] ? $text : vsprintf($text, $parameters);
		});
	}

	private function getApplier(): LegalInformationFilePolicyApplier {
		return new LegalInformationFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
		);
	}

	public function testApplyStoresSnapshot(): void {
		$file = new \OCA\Libresign\Db\File();
		$legalInformationValue = 'Snapshot legal copy';

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(LegalInformationPolicy::KEY, null, [LegalInformationPolicy::KEY => $legalInformationValue])
			->willReturn($this->createResolvedPolicy($legalInformationValue, sourceScope: 'group'));

		$this->getApplier()->apply($file, [
			'policyOverrides' => [LegalInformationPolicy::KEY => $legalInformationValue],
		]);

		$this->assertSame([
			'policy_snapshot' => [
				LegalInformationPolicy::KEY => [
					'effectiveValue' => $legalInformationValue,
					'sourceScope' => 'group',
				],
			],
		], $file->getMetadata());
	}

	public function testApplyThrowsWhenRequestOverrideIsBlocked(): void {
		$file = new \OCA\Libresign\Db\File();
		$legalInformationValue = 'Blocked legal copy';

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(LegalInformationPolicy::KEY, null, [LegalInformationPolicy::KEY => $legalInformationValue])
			->willReturn($this->createResolvedPolicy(
				$legalInformationValue,
				sourceScope: 'group',
				canUseAsRequestOverride: false,
				blockedBy: 'group',
			));

		$this->expectException(LibresignException::class);
		$this->expectExceptionCode(422);

		$this->getApplier()->apply($file, [
			'policyOverrides' => [LegalInformationPolicy::KEY => $legalInformationValue],
		]);
	}

	public function testSyncPersistsWhenSnapshotChanges(): void {
		$file = new \OCA\Libresign\Db\File();
		$file->setUserId('john');
		$legalInformationValue = 'Request legal copy';

		$this->policyService
			->expects($this->once())
			->method('resolveForUserId')
			->with(LegalInformationPolicy::KEY, 'john', [LegalInformationPolicy::KEY => $legalInformationValue])
			->willReturn($this->createResolvedPolicy($legalInformationValue, sourceScope: 'request'));

		$this->fileService
			->expects($this->once())
			->method('update')
			->with($this->identicalTo($file));

		$this->getApplier()->sync($file, [
			'policyOverrides' => [LegalInformationPolicy::KEY => $legalInformationValue],
		]);
	}

	private function createResolvedPolicy(
		string $effectiveValue,
		string $sourceScope = 'system',
		bool $canUseAsRequestOverride = true,
		?string $blockedBy = null,
	): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey(LegalInformationPolicy::KEY)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope)
			->setCanUseAsRequestOverride($canUseAsRequestOverride)
			->setBlockedBy($blockedBy);
	}
}
