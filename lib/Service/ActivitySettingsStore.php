<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

class ActivitySettingsStore {
	private const USER_SETTINGS_CLASS = 'OCA\\Activity\\UserSettings';

	private bool $resolved = false;
	private ?object $activityUserSettings = null;

	public function isAvailable(): bool {
		return $this->getActivityUserSettings() !== null;
	}

	public function hasSetting(string $type): bool {
		if (!$this->isAvailable()) {
			return false;
		}

		try {
			$manager = \OCP\Server::get(\OCP\Activity\IManager::class);
			$manager->getSettingById($type);
			return true;
		} catch (\Throwable) {
			return false;
		}
	}

	public function getAdminSetting(string $channel, string $type): mixed {
		return $this->getActivityUserSettings()?->getAdminSetting($channel, $type);
	}

	public function getUserSetting(string $userId, string $channel, string $type): mixed {
		return $this->getActivityUserSettings()?->getUserSetting($userId, $channel, $type);
	}

	private function getActivityUserSettings(): ?object {
		if ($this->resolved) {
			return $this->activityUserSettings;
		}

		$this->resolved = true;
		if (!class_exists(self::USER_SETTINGS_CLASS)) {
			return null;
		}

		try {
			$activityUserSettings = \OCP\Server::get(self::USER_SETTINGS_CLASS);
			if (is_object($activityUserSettings)) {
				$this->activityUserSettings = $activityUserSettings;
			}
		} catch (\Throwable) {
			$this->activityUserSettings = null;
		}

		return $this->activityUserSettings;
	}
}
