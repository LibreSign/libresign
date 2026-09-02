<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignerGeolocation\FilePolicy;

use OCA\Libresign\Service\FileService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignerGeolocation\FilePolicy\SignerGeolocationFilePolicyApplier;
use OCA\Libresign\Service\Policy\Provider\SignerGeolocation\SignerGeolocationPolicy;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;

final class SignerGeolocationFilePolicyApplierTest extends \OCA\Libresign\Tests\Unit\TestCase {
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

	private function getApplier(): SignerGeolocationFilePolicyApplier {
		return new SignerGeolocationFilePolicyApplier(
			$this->policyService,
			$this->fileService,
			$this->l10n,
		);
	}

	public function testApplyStoresSnapshot(): void {
		$file = new \OCA\Libresign\Db\File();
		$policyValue = [
			'mode' => 'optional',
		];

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(
				SignerGeolocationPolicy::KEY,
				null,
				[SignerGeolocationPolicy::KEY => $policyValue],
			)
			->willReturn($this->createResolvedPolicy($policyValue, sourceScope: 'system'));

		$this->getApplier()->apply($file, [
			'policyOverrides' => [SignerGeolocationPolicy::KEY => $policyValue],
		]);

		$this->assertSame([
			'policy_snapshot' => [
				SignerGeolocationPolicy::KEY => [
					'effectiveValue' => $policyValue,
					'sourceScope' => 'system',
				],
			],
		], $file->getMetadata());
	}

	public function testApplyThrowsWhenRequestOverrideIsBlocked(): void {
		$file = new \OCA\Libresign\Db\File();
		$policyValue = [
			'mode' => 'required',
		];

		$this->policyService
			->expects($this->once())
			->method('resolveForUser')
			->with(SignerGeolocationPolicy::KEY, null, [SignerGeolocationPolicy::KEY => $policyValue])
			->willReturn($this->createResolvedPolicy(
				$policyValue,
				sourceScope: 'system',
				canUseAsRequestOverride: false,
				blockedBy: 'system',
			));

		$this->expectException(\OCA\Libresign\Exception\LibresignException::class);
		$this->expectExceptionCode(422);

		$this->getApplier()->apply($file, [
			'policyOverrides' => [SignerGeolocationPolicy::KEY => $policyValue],
		]);
	}

	private function createResolvedPolicy(
		array $effectiveValue,
		string $sourceScope = 'system',
		bool $canUseAsRequestOverride = true,
		?string $blockedBy = null,
	): ResolvedPolicy {
		return (new ResolvedPolicy())
			->setPolicyKey(SignerGeolocationPolicy::KEY)
			->setEffectiveValue($effectiveValue)
			->setSourceScope($sourceScope)
			->setCanUseAsRequestOverride($canUseAsRequestOverride)
			->setBlockedBy($blockedBy);
	}
}
