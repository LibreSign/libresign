<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\DocMdp;

use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Service\DocMdp\ConfigService;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConfigServiceTest extends TestCase {
	private PolicyService&MockObject $policyService;
	private IL10N&MockObject $l10n;

	protected function setUp(): void {
		$this->policyService = $this->createMock(PolicyService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	private function createService(): ConfigService {
		return new ConfigService($this->policyService, $this->l10n);
	}

	#[DataProvider('providerGetLevelCases')]
	public function testGetLevelReturnsExpectedEnum(int|string|null $storedLevel, DocMdpLevel $expectedLevel): void {
		$this->policyService
			->expects($this->once())
			->method('getSystemPolicy')
			->with('docmdp')
			->willReturn($storedLevel === null
				? null
				: (new PolicyLayer())
					->setScope('global')
					->setValue($storedLevel));

		$service = $this->createService();

		$this->assertSame($expectedLevel, $service->getLevel());
	}

	#[DataProvider('providerGetConfigCases')]
	public function testGetConfigReturnsExpectedState(int|string|null $storedLevel, bool $expectedEnabled, int $expectedDefaultLevel): void {
		$this->policyService
			->method('getSystemPolicy')
			->with('docmdp')
			->willReturn($storedLevel === null
				? null
				: (new PolicyLayer())
					->setScope('global')
					->setValue($storedLevel));

		$service = $this->createService();
		$config = $service->getConfig();

		$this->assertSame($expectedEnabled, $config['enabled']);
		$this->assertSame($expectedDefaultLevel, $config['defaultLevel']);
	}

	public function testSetEnabledFalseSetsNotCertifiedLevel(): void {
		$this->policyService
			->expects($this->once())
			->method('getSystemPolicy')
			->with('docmdp')
			->willReturn((new PolicyLayer())
				->setScope('global')
				->setValue(DocMdpLevel::CERTIFIED_FORM_FILLING->value)
				->setAllowChildOverride(true));

		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with('docmdp', DocMdpLevel::NOT_CERTIFIED->value, true)
			->willReturn((new ResolvedPolicy())
				->setPolicyKey('docmdp')
				->setEffectiveValue(DocMdpLevel::NOT_CERTIFIED->value));

		$service = $this->createService();
		$service->setEnabled(false);
	}

	public function testSetEnabledTrueRestoresDefaultLevelWhenCurrentlyDisabled(): void {
		$this->policyService
			->expects($this->exactly(2))
			->method('getSystemPolicy')
			->with('docmdp')
			->willReturn((new PolicyLayer())
				->setScope('global')
				->setValue(DocMdpLevel::NOT_CERTIFIED->value)
				->setAllowChildOverride(true));

		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with('docmdp', DocMdpLevel::CERTIFIED_FORM_FILLING->value, true)
			->willReturn((new ResolvedPolicy())
				->setPolicyKey('docmdp')
				->setEffectiveValue(DocMdpLevel::CERTIFIED_FORM_FILLING->value));

		$service = $this->createService();
		$service->setEnabled(true);
	}

	public function testSetEnabledTrueKeepsCurrentLevelWhenAlreadyEnabled(): void {
		$this->policyService
			->expects($this->once())
			->method('getSystemPolicy')
			->with('docmdp')
			->willReturn((new PolicyLayer())
				->setScope('global')
				->setValue(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value));

		$this->policyService
			->expects($this->never())
			->method('saveSystem');

		$service = $this->createService();
		$service->setEnabled(true);
	}

	public static function providerGetLevelCases(): array {
		return [
			'policy default falls back to not certified when unset' => [
				null,
				DocMdpLevel::NOT_CERTIFIED,
			],
			'explicit no changes level' => [
				DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value,
				DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED,
			],
			'explicit annotations level' => [
				DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->value,
				DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS,
			],
			'string encoded levels are normalized' => [
				'2',
				DocMdpLevel::CERTIFIED_FORM_FILLING,
			],
		];
	}

	public static function providerGetConfigCases(): array {
		return [
			'not configured defaults to disabled and level 0' => [
				null,
				false,
				0,
			],
			'explicitly disabled with level 0' => [
				DocMdpLevel::NOT_CERTIFIED->value,
				false,
				0,
			],
			'configured certifying level is reflected as enabled' => [
				DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value,
				true,
				1,
			],
		];
	}
}
