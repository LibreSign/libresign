<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Identify;

use OCA\Libresign\Collaboration\Collaborators\AccountPhonePlugin;
use OCA\Libresign\Collaboration\Collaborators\ContactPhonePlugin;
use OCA\Libresign\Collaboration\Collaborators\ManualPhonePlugin;
use OCA\Libresign\Collaboration\Collaborators\SignerPlugin;
use OCP\Share\IShare;

class ResultFormatter {
	public function formatForNcSelect(array $list): array {
		$formattedList = [];
		foreach ($list as $key => $item) {
			$shareType = $item['value']['shareType'];
			$method = $item['method'] ?? '';

			// Determine if this is not a regular user account
			$isNoUser = $shareType !== IShare::TYPE_USER;
			if ($isNoUser && $method === 'account') {
				$isNoUser = false;
			}

			$formattedList[$key] = [
				'id' => $item['value']['shareWith'],
				'isNoUser' => $isNoUser,
				'displayName' => $item['label'],
				'subname' => $item['shareWithDisplayNameUnique'] ?? '',
			];

			if ($shareType === IShare::TYPE_EMAIL) {
				$formattedList[$key]['method'] = 'email';
				$formattedList[$key]['icon'] = 'icon-mail';
			} elseif ($shareType === IShare::TYPE_USER) {
				$formattedList[$key]['method'] = 'account';
				$formattedList[$key]['icon'] = 'icon-user';
			} elseif (in_array($shareType, [
				SignerPlugin::TYPE_SIGNER,
				AccountPhonePlugin::TYPE_SIGNER_ACCOUNT_PHONE,
				ContactPhonePlugin::TYPE_SIGNER_CONTACT_PHONE,
				ManualPhonePlugin::TYPE_SIGNER_MANUAL_PHONE,
			], true)) {
				$method = $item['method'] ?? '';
				$formattedList[$key]['method'] = $method;

				if ($method === 'email') {
					$formattedList[$key]['icon'] = 'icon-mail';
				} elseif ($method === 'account') {
					$formattedList[$key]['icon'] = 'icon-user';
				} else {
					$formattedList[$key]['iconSvg'] = 'svg' . ucfirst($method);
					$formattedList[$key]['iconName'] = $method;
				}
			}
		}
		return $formattedList;
	}

	public function replaceShareTypeWithMethod(array $list): array {
		foreach ($list as $key => $item) {
			if (isset($item['method']) && !empty($item['method'])) {
				unset($list[$key]['shareType']);
				continue;
			}
			$list[$key]['method'] = match ($item['shareType']) {
				IShare::TYPE_EMAIL => 'email',
				IShare::TYPE_USER => 'account',
				default => '',
			};
			unset($list[$key]['shareType']);
		}
		return $list;
	}
}
