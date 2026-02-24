<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\DocMdp;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Service\DocMdp\ConfigService;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConfigServiceTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private IL10N&MockObject $l10n;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
	}

	private function createService(): ConfigService {
		return new ConfigService($this->appConfig, $this->l10n);
	}

	#[DataProvider('providerGetLevelCases')]
	public function testGetLevelReturnsExpectedEnum(int $storedLevel, DocMdpLevel $expectedLevel): void {
		$this->appConfig
			->expects($this->once())
			->method('getValueInt')
			->with(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_FORM_FILLING->value)
			->willReturn($storedLevel);

		$service = $this->createService();

		$this->assertSame($expectedLevel, $service->getLevel());
	}

	#[DataProvider('providerGetConfigCases')]
	public function testGetConfigReturnsExpectedState(int $storedLevel, bool $expectedEnabled, int $expectedDefaultLevel): void {
		$this->appConfig
			->method('getValueInt')
			->with(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_FORM_FILLING->value)
			->willReturn($storedLevel);

		$service = $this->createService();
		$config = $service->getConfig();

		$this->assertSame($expectedEnabled, $config['enabled']);
		$this->assertSame($expectedDefaultLevel, $config['defaultLevel']);
	}

	public function testSetEnabledFalseSetsNotCertifiedLevel(): void {
		$this->appConfig
			->expects($this->once())
			->method('setValueInt')
			->with(Application::APP_ID, 'docmdp_level', DocMdpLevel::NOT_CERTIFIED->value);

		$service = $this->createService();
		$service->setEnabled(false);
	}

	public function testSetEnabledTrueRestoresDefaultLevelWhenCurrentlyDisabled(): void {
		$this->appConfig
			->expects($this->once())
			->method('getValueInt')
			->with(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_FORM_FILLING->value)
			->willReturn(DocMdpLevel::NOT_CERTIFIED->value);

		$this->appConfig
			->expects($this->once())
			->method('setValueInt')
			->with(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_FORM_FILLING->value);

		$service = $this->createService();
		$service->setEnabled(true);
	}

	public function testSetEnabledTrueKeepsCurrentLevelWhenAlreadyEnabled(): void {
		$this->appConfig
			->expects($this->once())
			->method('getValueInt')
			->with(Application::APP_ID, 'docmdp_level', DocMdpLevel::CERTIFIED_FORM_FILLING->value)
			->willReturn(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value);

		$this->appConfig
			->expects($this->never())
			->method('setValueInt');

		$service = $this->createService();
		$service->setEnabled(true);
	}

	public static function providerGetLevelCases(): array {
		return [
			'default/fallback level is form filling' => [
				DocMdpLevel::CERTIFIED_FORM_FILLING->value,
				DocMdpLevel::CERTIFIED_FORM_FILLING,
			],
			'explicit no changes level' => [
				DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value,
				DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED,
			],
			'explicit annotations level' => [
				DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->value,
				DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS,
			],
		];
	}

	public static function providerGetConfigCases(): array {
		return [
			'not configured defaults to enabled and level 2' => [
				DocMdpLevel::CERTIFIED_FORM_FILLING->value,
				true,
				2,
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
