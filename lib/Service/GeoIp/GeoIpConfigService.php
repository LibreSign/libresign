<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\GeoIp;

use OCA\Libresign\AppInfo\Application;
use OCP\IAppConfig;

class GeoIpConfigService {
	public const APP_CONFIG_KEY = 'geoip_database_path';

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function getDatabasePath(): ?string {
		$path = trim($this->appConfig->getValueString(Application::APP_ID, self::APP_CONFIG_KEY));
		return $path === '' ? null : $path;
	}

	public function setDatabasePath(?string $path): void {
		$normalized = $path === null ? '' : trim($path);
		$this->appConfig->setValueString(Application::APP_ID, self::APP_CONFIG_KEY, $normalized);
	}
}
