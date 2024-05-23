<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Service\MailService;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;

class TokenService {
	public const TOKEN_LENGTH = 6;
	public const SIGN_PASSWORD = 'password';
	public const SIGN_SIGNAL = 'signal';
	public const SIGN_TELEGRAM = 'telegram';
	public const SIGN_SMS = 'sms';
	public const SIGN_EMAIL = 'email';

	public function __construct(
		private ISecureRandom $secureRandom,
		private IHasher $hasher,
		private MailService $mail,
	) {
	}

	/**
	 * @todo check this code and put to work
	 */
	public function sendCodeByGateway(string $code, string $gatewayName): void {
		// 	$user = \OCP\Server::get(IUserSession::class)->getUser();
		// 	$gateway = $this->getGateway($user, $gatewayName);

		// 	$userAccount = $this->accountManager->getAccount($user);
		// 	$identifier = $userAccount->getProperty(IAccountManager::PROPERTY_PHONE)->getValue();
		// 	$gateway->send($user, $identifier, $this->l10n->t('%s is your LibreSign verification code.', $code));
		// }

		// /**
		//  * @throws OCSForbiddenException
		//  */
		// private function getGateway(IUser $user, string $gatewayName): \OCA\TwoFactorGateway\Service\Gateway\IGateway {
		// 	if (!$this->appManager->isEnabledForUser('twofactor_gateway', $user)) {
		// 		throw new OCSForbiddenException($this->l10n->t('Authorize signing using %s token is disabled because Nextcloud Two-Factor Gateway is not enabled.', $gatewayName));
		// 	}
		// 	$factory = $this->serverContainer->get('\OCA\TwoFactorGateway\Service\Gateway\Factory');
		// 	$gateway = $factory->getGateway($gatewayName);
		// 	if (!$gateway->getConfig()->isComplete()) {
		// 		throw new OCSForbiddenException($this->l10n->t('Gateway %s not configured on Two-Factor Gateway.', $gatewayName));
		// 	}
		// 	return $gateway;
	}

	public function sendCodeByEmail(string $email, string $displayName): string {
		$code = $this->secureRandom->generate(self::TOKEN_LENGTH, ISecureRandom::CHAR_DIGITS);
		$this->mail->sendCodeToSign(
			email: $email,
			name: $displayName,
			code: $code
		);
		return $this->hasher->hash($code);
	}
}
