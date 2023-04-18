<?php

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

declare(strict_types=1);

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Db\FileUserMapper;
use OCA\Libresign\Exception\LibresignException;
use OCP\Accounts\IAccountManager;
use OCP\App\IAppManager;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use Psr\Container\ContainerInterface;

class SignMethodService {
	public const SIGN_PASSWORD = 'password';
	public const SIGN_SIGNAL = 'signal';
	public const SIGN_TELEGRAM = 'telegram';
	public const SIGN_SMS = 'sms';
	public const SIGN_EMAIL = 'email';
	public const SIGN_METHODS = [
		self::SIGN_PASSWORD,
	];

	public function __construct(
		private FileUserMapper $fileUserMapper,
		private IAccountManager $accountManager,
		private IAppManager $appManager,
		private IConfig $config,
		private IL10N $l10n,
		private ISecureRandom $secureRandom,
		private IHasher $hasher,
		private ContainerInterface $serverContainer,
		private MailService $mail
	) {
	}

	public function getUserSignMethod(array $user): string {
		if (array_key_exists('signMethod', $user)) {
			return $user['signMethod'];
		}
		return $this->config->getAppValue(Application::APP_ID, 'sign_method', 'password') ?? 'password';
	}

	public function requestCode(FileUser $fileUser, IUser $user): string {
		return $this->requestCode($fileUser, $user);
		$token = $this->secureRandom->generate(6, ISecureRandom::CHAR_DIGITS);
		$this->sendCode($user, $fileUser, $token);
		$fileUser->setCode($this->hasher->hash($token));
		$this->fileUserMapper->update($fileUser);
		return $token;
	}

	private function sendCode(IUser $user, FileUser $fileUser, string $code): void {
		$signMethod = $this->config->getAppValue(Application::APP_ID, 'sign_method', 'password');
		switch ($signMethod) {
			case SignMethodService::SIGN_SMS:
			case SignMethodService::SIGN_TELEGRAM:
			case SignMethodService::SIGN_SIGNAL:
				$this->sendCodeByGateway($user, $code, $signMethod);
				break;
			case SignMethodService::SIGN_EMAIL:
				$this->sendCodeByEmail($fileUser, $code);
				break;
			case SignMethodService::SIGN_PASSWORD:
				throw new LibresignException($this->l10n->t('Sending authorization code not enabled.'));
		}
	}

	private function sendCodeByGateway(IUser $user, string $code, string $gatewayName): void {
		$gateway = $this->getGateway($user, $gatewayName);
		
		$userAccount = $this->accountManager->getAccount($user);
		$identifier = $userAccount->getProperty(IAccountManager::PROPERTY_PHONE)->getValue();
		$gateway->send($user, $identifier, $this->l10n->t('%s is your LibreSign verification code.', $code));
	}

	/**
	 * @throws OCSForbiddenException
	 */
	private function getGateway(IUser $user, string $gatewayName): \OCA\TwoFactorGateway\Service\Gateway\IGateway {
		if (!$this->appManager->isEnabledForUser('twofactor_gateway', $user)) {
			throw new OCSForbiddenException($this->l10n->t('Authorize signing using %s token is disabled because Nextcloud Two-Factor Gateway is not enabled.', $gatewayName));
		}
		$factory = $this->serverContainer->get('\OCA\TwoFactorGateway\Service\Gateway\Factory');
		$gateway = $factory->getGateway($gatewayName);
		if (!$gateway->getConfig()->isComplete()) {
			throw new OCSForbiddenException($this->l10n->t('Gateway %s not configured on Two-Factor Gateway.', $gatewayName));
		}
		return $gateway;
	}

	private function sendCodeByEmail(FileUser $fileUser, string $code): void {
		$this->mail->sendCodeToSign($fileUser, $code);
	}
}
