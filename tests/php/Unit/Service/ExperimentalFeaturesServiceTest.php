<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ExperimentalFeaturesServiceTest extends TestCase {
	/**
	 * @return array<string, array{?bool, bool}>
	 */
	public static function provideExperimentalFeatureStates(): array {
		return [
			'missing configuration' => [null, false],
			'explicitly disabled' => [false, false],
			'explicitly enabled' => [true, true],
		];
	}

	#[DataProvider('provideExperimentalFeatureStates')]
	public function testExperimentalFeaturesSwitch(?bool $configuredValue, bool $expected): void {
		$appConfig = self::getMockAppConfig();

		if ($configuredValue !== null) {
			$appConfig->setValueBool(
				Application::APP_ID,
				ExperimentalFeaturesService::APP_CONFIG_KEY,
				$configuredValue,
			);
		}

		$service = new ExperimentalFeaturesService($appConfig);

		$this->assertSame($expected, $service->isEnabled());
	}
}
