<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\Signing\ExternalIntegrationAvailability;
use OCA\Libresign\Service\Signing\ExternalIntegrationRecord;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ExternalIntegrationAvailabilityTest extends TestCase {
	/** @return array<string, array{bool, bool, bool, bool}> */
	public static function flags(): array {
		$result = [];
		foreach ([false, true] as $global) {
			foreach ([false, true] as $enabled) {
				foreach ([false, true] as $available) {
					$key = sprintf('global=%d,enabled=%d,available=%d', (int)$global, (int)$enabled, (int)$available);
					$result[$key] = [$global, $enabled, $available, $global && $enabled && $available];
				}
			}
		}
		return $result;
	}

	#[DataProvider('flags')]
	public function testGateCannotOverrideOtherIndependentConditions(
		bool $global,
		bool $enabled,
		bool $available,
		bool $expected,
	): void {
		$appConfig = self::getMockAppConfig();
		$appConfig->setValueBool(Application::APP_ID, ExperimentalFeaturesService::APP_CONFIG_KEY, $global);
		$record = new ExternalIntegrationRecord(
			str_repeat('a', 32), 'csc', 'CSC instance', [], $enabled, false, 1,
			'2026-11-21T00:00:00+00:00', '2026-11-21T00:00:00+00:00',
		);
		$gate = new ExternalIntegrationAvailability(new ExperimentalFeaturesService($appConfig));
		$this->assertSame($expected, $gate->canStartSigning($record, $available));
	}
}
