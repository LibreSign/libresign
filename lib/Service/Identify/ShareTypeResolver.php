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
use OCA\Libresign\Service\IdentifyMethod\Account;
use OCA\Libresign\Service\IdentifyMethod\Email;
use OCP\Share\IShare;

class ShareTypeResolver {
	private const PHONE_METHODS = ['whatsapp', 'sms', 'telegram', 'signal'];

	public function __construct(
		private Email $identifyEmailMethod,
		private Account $identifyAccountMethod,
	) {
	}

	public function resolve(string $method = ''): array {
		$normalizedMethod = strtolower(trim($method));
		$isAllMethods = $normalizedMethod === '' || $normalizedMethod === 'all';
		$includeAccount = $isAllMethods || $normalizedMethod === 'account';
		$includeEmail = $isAllMethods || $normalizedMethod === 'email';
		$includePhone = $isAllMethods || in_array($normalizedMethod, self::PHONE_METHODS, true);

		$shareTypes = [];
		if ($includeEmail) {
			$settings = $this->identifyEmailMethod->getSettings();
			if ($settings['enabled']) {
				$shareTypes[] = IShare::TYPE_EMAIL;
			}
		}

		if ($includeAccount) {
			$settings = $this->identifyAccountMethod->getSettings();
			if ($settings['enabled']) {
				$shareTypes[] = IShare::TYPE_USER;
			}
		}

		$shareTypes[] = SignerPlugin::TYPE_SIGNER;

		if ($includePhone) {
			$shareTypes[] = AccountPhonePlugin::TYPE_SIGNER_ACCOUNT_PHONE;
			$shareTypes[] = ContactPhonePlugin::TYPE_SIGNER_CONTACT_PHONE;
			$shareTypes[] = ManualPhonePlugin::TYPE_SIGNER_MANUAL_PHONE;
		}

		return $shareTypes;
	}
}
