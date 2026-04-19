<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

require_once __DIR__ . '/../../../../lib/Migration/Version18001Date20260320000000.php';

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Migration\Version18001Date20260320000000;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class Version18001Date20260320000000Test extends TestCase {
	private IAppConfig&MockObject $appConfig;

	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
	}

	public function testMigratesLegacyFooterSettingsIntoStructuredPayload(): void {
		$this->appConfig
			->expects($this->exactly(7))
			->method('getValueString')
			->willReturnMap([
				[Application::APP_ID, 'add_footer', '', '1'],
				[Application::APP_ID, 'write_qrcode_on_footer', '', '0'],
				[Application::APP_ID, 'validation_site', '', 'https://validator.example/base/'],
				[Application::APP_ID, 'footer_template_is_default', '', '0'],
				[Application::APP_ID, 'docmdp_level', '', ''],
				[Application::APP_ID, 'groups_request_sign', '', ''],
				[Application::APP_ID, 'identify_methods', '', ''],
			]);

		$deletedKeys = [];

		$this->appConfig
			->expects($this->once())
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deletedKeys): bool {
				$deletedKeys[] = [$app, $key];
				return true;
			});

		$this->appConfig
			->expects($this->once())
			->method('setValueString')
			->with(
				Application::APP_ID,
				'add_footer',
				FooterPolicyValue::encode([
					'enabled' => true,
					'writeQrcodeOnFooter' => false,
					'validationSite' => 'https://validator.example/base/',
					'customizeFooterTemplate' => true,
				]),
			);

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		self::assertSame([
			[Application::APP_ID, 'add_footer'],
		], $deletedKeys);
	}

	public function testReadsLegacyBooleanWhenAddFooterHasTypedBoolValue(): void {
		$this->appConfig
			->expects($this->exactly(7))
			->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default): string {
				if ($app !== Application::APP_ID) {
					return $default;
				}

				if ($key === 'add_footer') {
					throw new AppConfigTypeConflictException('bool stored');
				}

				if ($key === 'write_qrcode_on_footer') {
					return '1';
				}

				if ($key === 'validation_site') {
					return '';
				}

				if ($key === 'footer_template_is_default') {
					return '1';
				}

				if ($key === 'docmdp_level' || $key === 'identify_methods') {
					return '';
				}

				if ($key === 'groups_request_sign') {
					return '';
				}

				return $default;
			});

		$this->appConfig
			->expects($this->once())
			->method('getValueBool')
			->with(Application::APP_ID, 'add_footer', true)
			->willReturn(false);

		$deletedKeys = [];

		$this->appConfig
			->expects($this->once())
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deletedKeys): bool {
				$deletedKeys[] = [$app, $key];
				return true;
			});

		$this->appConfig
			->expects($this->once())
			->method('setValueString')
			->with(
				Application::APP_ID,
				'add_footer',
				FooterPolicyValue::encode([
					'enabled' => false,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
				]),
			);

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		self::assertSame([
			[Application::APP_ID, 'add_footer'],
		], $deletedKeys);
	}

	public function testMigratesGroupsRequestSignFromTypedArrayToCanonicalString(): void {
		$this->appConfig
			->method('getValueString')
			->willReturnCallback(static function (string $app, string $key, string $default): string {
				if ($app !== Application::APP_ID) {
					return $default;
				}

				if ($key === 'groups_request_sign') {
					throw new AppConfigTypeConflictException('array stored');
				}

				return '';
			});

		$this->appConfig
			->expects($this->once())
			->method('getValueArray')
			->with(Application::APP_ID, 'groups_request_sign', ['admin'])
			->willReturn(['finance', 'admin']);

		$deletedKeys = [];
		$this->appConfig
			->expects($this->exactly(2))
			->method('deleteKey')
			->willReturnCallback(static function (string $app, string $key) use (&$deletedKeys): bool {
				$deletedKeys[] = [$app, $key];
				return true;
			});

		$this->appConfig
			->expects($this->exactly(2))
			->method('setValueString')
			->willReturnCallback(static function (string $app, string $key, string $value): bool {
				if ($key === 'groups_request_sign') {
					TestCase::assertSame('["admin","finance"]', $value);
				}

				return true;
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		self::assertContains([Application::APP_ID, 'groups_request_sign'], $deletedKeys);
	}
}
