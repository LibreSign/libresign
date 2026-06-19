<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCP\Config\IUserConfig;
use OCP\IAppConfig;

class NotificationPreferenceResolver {
	public function __construct(
		private IAppConfig $appConfig,
		private IUserConfig $userConfig,
		private ActivitySettingsStore $activitySettingsStore,
	) {
	}

	public function isEmailNotificationDisabled(
		string $userId,
		string $type,
		bool $requireKnownActivitySetting = true,
	): bool {
		return $this->isNotificationDisabled($userId, 'email', $type, $requireKnownActivitySetting);
	}

	public function isInAppNotificationDisabled(
		string $userId,
		string $type,
		bool $requireKnownActivitySetting = true,
	): bool {
		return $this->isNotificationDisabled($userId, 'notification', $type, $requireKnownActivitySetting);
	}

	private function isNotificationDisabled(
		string $userId,
		string $channel,
		string $type,
		bool $requireKnownActivitySetting,
	): bool {
		if ($this->activitySettingsStore->isAvailable()) {
			if ($requireKnownActivitySetting && !$this->activitySettingsStore->hasSetting($type)) {
				return false;
			}

			$adminSetting = $this->activitySettingsStore->getAdminSetting($channel, $type);
			if (!$this->isActivitySettingEnabled($adminSetting)) {
				return true;
			}

			$userSetting = $this->activitySettingsStore->getUserSetting($userId, $channel, $type);
			if (!$this->isActivitySettingEnabled($userSetting)) {
				return true;
			}

			return false;
		}

		$configKey = sprintf('notify_%s_%s', $channel, $type);
		if (!$this->isActivitySettingEnabled($this->appConfig->getValueString('activity', $configKey, '1'))) {
			return true;
		}

		return !$this->isActivitySettingEnabled(
			$this->userConfig->getValueString($userId, 'activity', $configKey, '1')
		);
	}

	private function isActivitySettingEnabled(mixed $setting): bool {
		if (is_bool($setting)) {
			return $setting;
		}
		if (is_int($setting)) {
			return $setting === 1;
		}
		if ($setting === null) {
			return true;
		}

		$normalized = strtolower(trim((string)$setting));
		return !in_array($normalized, ['', '0', 'false', 'off', 'no'], true);
	}
}
