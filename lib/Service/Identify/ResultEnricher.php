<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Identify;

use OCA\Libresign\Service\IdentifyMethod\Account;
use OCA\Libresign\Service\IdentifyMethod\Email;
use OCP\Config\IUserConfig;
use OCP\IAppConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

class ResultEnricher {
	public function __construct(
		private IUserSession $userSession,
		private IUserManager $userManager,
		private Email $identifyEmailMethod,
		private Account $identifyAccountMethod,
		private IAppConfig $appConfig,
		private IUserConfig $userConfig,
	) {
	}

	public function addHerselfAccount(array $return, string $search, string $method = ''): array {
		if (!empty($method) && $method !== 'account') {
			return $return;
		}

		$settings = $this->identifyAccountMethod->getSettings();
		if (empty($settings['enabled'])) {
			return $return;
		}

		$user = $this->userSession->getUser();
		$searchLower = strtolower($search);

		if (!$this->userMatchesSearch($user, $searchLower)) {
			return $return;
		}

		$filtered = array_filter($return, fn ($i) => $i['identify'] === $user->getUID());
		if (count($filtered)) {
			return $return;
		}

		$return[] = [
			'identify' => $user->getUID(),
			'isNoUser' => false,
			'displayName' => $user->getDisplayName(),
			'subname' => $user->getEMailAddress(),
			'iconName' => 'account',
			'method' => 'account',
		];

		return $return;
	}

	public function addHerselfEmail(array $return, string $search, string $method = ''): array {
		if (!empty($method) && $method !== 'email') {
			return $return;
		}

		$settings = $this->identifyEmailMethod->getSettings();
		if (empty($settings['enabled'])) {
			return $return;
		}

		$user = $this->userSession->getUser();
		if (empty($user->getEMailAddress())) {
			return $return;
		}

		if (!str_contains($user->getEMailAddress(), $search)
			&& !str_contains($user->getDisplayName(), $search)
		) {
			return $return;
		}

		$filtered = array_filter($return, fn ($i) => $i['identify'] === $user->getUID());
		if (count($filtered)) {
			return $return;
		}

		$return[] = [
			'identify' => $user->getEMailAddress(),
			'isNoUser' => true,
			'displayName' => $user->getDisplayName(),
			'subname' => $user->getEMailAddress(),
			'iconName' => 'email',
			'method' => 'email',
		];

		return $return;
	}

	public function addEmailNotificationPreference(array $list): array {
		foreach ($list as $key => $item) {
			if ($item['method'] !== 'account') {
				continue;
			}

			$user = $this->userManager->get($item['identify']);
			if ($user === null) {
				continue;
			}

			$email = $user->getEMailAddress();
			if (empty($email)) {
				continue;
			}

			$acceptsNotifications = !$this->isNotificationDisabledAtActivity($user->getUID(), 'libresign_file_to_sign');

			if ($acceptsNotifications) {
				$list[$key]['emailAddress'] = $email;
			}
			$list[$key]['acceptsEmailNotifications'] = $acceptsNotifications;
		}
		return $list;
	}

	private function userMatchesSearch(IUser $user, string $searchLower): bool {
		return str_contains($user->getUID(), $searchLower)
			|| str_contains(strtolower($user->getDisplayName()), $searchLower)
			|| ($user->getEMailAddress() !== null && str_contains($user->getEMailAddress(), $searchLower));
	}

	private function isNotificationDisabledAtActivity(string $userId, string $type): bool {
		$key = sprintf('notify_email_%s', $type);

		$adminSetting = $this->appConfig->getValueString('activity', $key, '1');
		if (!$this->isTruthySetting($adminSetting)) {
			return true;
		}

		$userSetting = $this->userConfig->getValueString($userId, 'activity', $key, '');
		if (!$this->isTruthySetting($userSetting)) {
			return true;
		}

		return false;
	}

	private function isTruthySetting(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value) || is_float($value)) {
			return (int)$value === 1;
		}

		if (!is_string($value)) {
			return false;
		}

		return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
	}
}
