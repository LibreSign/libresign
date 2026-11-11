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

final class ExperimentalFeaturesServiceTest extends TestCase {
	public function testExperimentalFeaturesAreDisabledWhenConfigurationIsMissing(): void {
		$service = new ExperimentalFeaturesService(self::getMockAppConfig());

		$this->assertFalse($service->isEnabled());
	}

	public function testExperimentalFeaturesAreDisabledWhenConfigurationIsFalse(): void {
		$appConfig = self::getMockAppConfig();
		$appConfig->setValueBool(Application::APP_ID, ExperimentalFeaturesService::APP_CONFIG_KEY, false);
		$service = new ExperimentalFeaturesService($appConfig);

		$this->assertFalse($service->isEnabled());
	}

	public function testExperimentalFeaturesAreEnabledWhenConfigurationIsTrue(): void {
		$appConfig = self::getMockAppConfig();
		$appConfig->setValueBool(Application::APP_ID, ExperimentalFeaturesService::APP_CONFIG_KEY, true);
		$service = new ExperimentalFeaturesService($appConfig);

		$this->assertTrue($service->isEnabled());
	}
}
