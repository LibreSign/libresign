<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Service\MailService;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IUser;
use OCP\IUserSession;
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
	// 	$user = \OC::$server->get(IUserSession::class)->getUser();
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
