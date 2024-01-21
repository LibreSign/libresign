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

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\ClickToSign;
use OCA\Libresign\Service\IdentifyMethod\Email;
use OCA\Libresign\Service\IdentifyMethod\Password;
use OCP\Accounts\IAccountManager;
use OCP\App\IAppManager;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use Psr\Container\ContainerInterface;

class SignatureMethodService {
	public const SIGN_PASSWORD = 'password';
	public const SIGN_SIGNAL = 'signal';
	public const SIGN_TELEGRAM = 'telegram';
	public const SIGN_SMS = 'sms';
	public const SIGN_EMAIL = 'email';

	public function __construct(
		private SignRequestMapper $signRequestMapper,
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

	public function getCurrent(): array {
		$signatureMethod = $this->config->getAppValue(Application::APP_ID, 'signature_method');
		$signatureMethod = json_decode($signatureMethod, true);
		if (!is_array($signatureMethod)) {
			$signatureMethods = $this->getAllowedMethods();
			foreach ($signatureMethods as $signatureMethod) {
				if ($signatureMethod['id'] === self::DEFAULT_SIGN_METHOD) {
					return $signatureMethod;
				}
			}
		}
		return $signatureMethod;
	}

	public function getAllowedMethods(): array {
		return [
			[
				'id' => 'password',
				'label' => \OC::$server->get(Password::class)->friendlyName,
			],
			[
				'id' => 'click-to-sign',
				'label' => \OC::$server->get(ClickToSign::class)->friendlyName,
			],
			[
				'id' => 'email',
				'label' => \OC::$server->get(Email::class)->friendlyName,
			],
		];
	}

	public function requestCode(SignRequest $signRequest, IUser $user, string $sendToEmail = ''): string {
		$token = $this->secureRandom->generate(6, ISecureRandom::CHAR_DIGITS);
		$this->sendCode($user, $token, $signRequest->getDisplayName(), $sendToEmail);
		$signRequest->setCode($this->hasher->hash($token));
		$this->signRequestMapper->update($signRequest);
		return $token;
	}

	private function sendCode(IUser $user, string $code, string $displayName, string $sendToEmail): void {
		$current = $this->getCurrent();
		$signMethod = $current['id'];
		switch ($signMethod) {
			case SignatureMethodService::SIGN_SMS:
			case SignatureMethodService::SIGN_TELEGRAM:
			case SignatureMethodService::SIGN_SIGNAL:
				$this->sendCodeByGateway($user, $code, $signMethod);
				break;
			case SignatureMethodService::SIGN_EMAIL:
				$this->sendCodeByEmail($sendToEmail, $displayName, $code);
				break;
			case SignatureMethodService::SIGN_PASSWORD:
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

	private function sendCodeByEmail(string $email, string $name, string $code): void {
		$this->mail->sendCodeToSign($email, $name, $code);
	}
}
