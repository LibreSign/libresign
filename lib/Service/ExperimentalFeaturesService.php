<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCP\IAppConfig;

class ExperimentalFeaturesService {
	public const APP_CONFIG_KEY = 'experimental_features';

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function isEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, self::APP_CONFIG_KEY, false);
	}
}
