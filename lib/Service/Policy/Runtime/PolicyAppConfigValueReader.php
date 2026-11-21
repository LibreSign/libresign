<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Runtime;

use OCA\Libresign\AppInfo\Application;
use OCP\Exceptions\AppConfigUnknownKeyException;
use OCP\IAppConfig;

/**
 * Read policy configuration using its persisted Nextcloud AppConfig type.
 */
final class PolicyAppConfigValueReader {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function readUsingStoredType(string $key, mixed $defaultValue): mixed {
		try {
			$type = $this->appConfig->getValueType(Application::APP_ID, $key, false);
		} catch (AppConfigUnknownKeyException) {
			return $defaultValue;
		}

		return match ($type & ~IAppConfig::VALUE_SENSITIVE) {
			IAppConfig::VALUE_INT => $this->appConfig->getValueInt(Application::APP_ID, $key, is_int($defaultValue) ? $defaultValue : 0, false),
			IAppConfig::VALUE_FLOAT => $this->appConfig->getValueFloat(Application::APP_ID, $key, is_float($defaultValue) ? $defaultValue : 0.0, false),
			IAppConfig::VALUE_BOOL => $this->appConfig->getValueBool(Application::APP_ID, $key, is_bool($defaultValue) ? $defaultValue : false, false),
			IAppConfig::VALUE_ARRAY => $this->appConfig->getValueArray(Application::APP_ID, $key, is_array($defaultValue) ? $defaultValue : [], false),
			default => $this->appConfig->getValueString(Application::APP_ID, $key, is_string($defaultValue) ? $defaultValue : '', false),
		};
	}
}
