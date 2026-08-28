<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignerGeolocation;

use OCA\Libresign\Db\File;
use OCA\Libresign\Enum\SignerGeolocationMode;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\SignerGeolocation\SignerGeolocationPolicy;
use OCA\Libresign\Service\SignerGeolocation\SignerGeolocationPolicyService;
use OCA\Libresign\Db\FileMapper;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SignerGeolocationPolicyServiceTest extends TestCase {
	private PolicyService&MockObject $policyService;
	private FileMapper&MockObject $fileMapper;
	private IL10N&MockObject $l10n;

	protected function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->fileMapper = $this->createMock(FileMapper::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	private function getService(): SignerGeolocationPolicyService {
		return new SignerGeolocationPolicyService(
			$this->policyService,
			$this->fileMapper,
			$this->l10n,
		);
	}

	public function testResolveEffectiveRequirementUsesRequesterOverride(): void {
		$file = new File();
		$file->setMetadata([
			'policy_snapshot' => [
				SignerGeolocationPolicy::KEY => [
					'effectiveValue' => [
						'mode' => 'optional',
						'allowRequesterOverride' => true,
					],
					'sourceScope' => 'system',
				],
			],
		]);

		$this->assertSame(
			SignerGeolocationMode::REQUIRED,
			$this->getService()->resolveEffectiveRequirement($file, true),
		);
		$this->assertSame(
			SignerGeolocationMode::OPTIONAL,
			$this->getService()->resolveEffectiveRequirement($file, false),
		);
	}

	public function testValidateRequesterConfigurationRejectsWhenDisabled(): void {
		$file = new File();
		$file->setMetadata([
			'policy_snapshot' => [
				SignerGeolocationPolicy::KEY => [
					'effectiveValue' => [
						'mode' => 'disabled',
						'allowRequesterOverride' => false,
					],
					'sourceScope' => 'system',
				],
			],
		]);

		$this->expectException(LibresignException::class);
		$this->getService()->validateRequesterConfiguration($file, true);
	}

	public function testGetPolicyValueFallsBackToLivePolicy(): void {
		$file = new File();
		$this->policyService
			->expects($this->once())
			->method('resolve')
			->with(SignerGeolocationPolicy::KEY)
			->willReturn((new ResolvedPolicy())
				->setPolicyKey(SignerGeolocationPolicy::KEY)
				->setEffectiveValue(['mode' => 'required', 'allowRequesterOverride' => false])
				->setSourceScope('system'));

		$this->assertSame([
			'mode' => 'required',
			'allowRequesterOverride' => false,
		], $this->getService()->getPolicyValue($file));
	}
}
