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
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
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

	public function testMigratesLegacySignatureFlowSystemKeysAndDeletesOldOnes(): void {
		$newSystemKey = SignatureFlowPolicy::SYSTEM_APP_CONFIG_KEY;
		$newAllowOverrideKey = $newSystemKey . '.allow_child_override';

		$this->appConfig
			->expects($this->exactly(4))
			->method('getValueString')
			->willReturnMap([
				[Application::APP_ID, 'signature_flow', '', 'ordered_numeric'],
				[Application::APP_ID, $newSystemKey, '', ''],
				[Application::APP_ID, 'signature_flow.allow_child_override', '', '1'],
				[Application::APP_ID, $newAllowOverrideKey, '', ''],
			]);

		$this->appConfig
			->expects($this->exactly(2))
			->method('setValueString')
			->willReturnCallback(static function (string $appId, string $key, string $value) use ($newSystemKey, $newAllowOverrideKey): bool {
				if ($appId !== Application::APP_ID) {
					return false;
				}
				if ($key === $newSystemKey && $value === 'ordered_numeric') {
					return true;
				}
				if ($key === $newAllowOverrideKey && $value === '1') {
					return true;
				}
				return false;
			});

		$deletedKeys = [];
		$this->appConfig
			->expects($this->exactly(2))
			->method('deleteKey')
			->willReturnCallback(static function (string $appId, string $key) use (&$deletedKeys): bool {
				if ($appId !== Application::APP_ID) {
					return false;
				}
				$deletedKeys[] = $key;
				return true;
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		$this->assertSame([
			'signature_flow',
			'signature_flow.allow_child_override',
		], $deletedKeys);
	}

	public function testDeletesLegacyKeysWithoutOverwritingNewValues(): void {
		$newSystemKey = SignatureFlowPolicy::SYSTEM_APP_CONFIG_KEY;
		$newAllowOverrideKey = $newSystemKey . '.allow_child_override';

		$this->appConfig
			->expects($this->exactly(4))
			->method('getValueString')
			->willReturnMap([
				[Application::APP_ID, 'signature_flow', '', 'ordered_numeric'],
				[Application::APP_ID, $newSystemKey, '', 'parallel'],
				[Application::APP_ID, 'signature_flow.allow_child_override', '', '1'],
				[Application::APP_ID, $newAllowOverrideKey, '', '0'],
			]);

		$this->appConfig
			->expects($this->never())
			->method('setValueString');

		$deletedKeys = [];
		$this->appConfig
			->expects($this->exactly(2))
			->method('deleteKey')
			->willReturnCallback(static function (string $appId, string $key) use (&$deletedKeys): bool {
				if ($appId !== Application::APP_ID) {
					return false;
				}
				$deletedKeys[] = $key;
				return true;
			});

		$migration = new Version18001Date20260320000000($this->appConfig);
		$migration->preSchemaChange($this->createMock(IOutput::class), static fn () => null, []);

		$this->assertSame([
			'signature_flow',
			'signature_flow.allow_child_override',
		], $deletedKeys);
	}
}
