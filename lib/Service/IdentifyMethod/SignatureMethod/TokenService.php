<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\MailService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IL10N;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use OCP\Server;
use Psr\Container\NotFoundExceptionInterface;

class TokenService {
	public const TOKEN_LENGTH = 6;

	public function __construct(
		private ISecureRandom $secureRandom,
		private IHasher $hasher,
		private MailService $mail,
		private IL10N $l10n,
	) {
	}

	public function sendCodeByGateway(string $identifier, string $gatewayName): string {
		$gateway = $this->getGateway($gatewayName);

		$code = $this->secureRandom->generate(self::TOKEN_LENGTH, ISecureRandom::CHAR_DIGITS);
		$gateway->send($identifier, $this->l10n->t('%s is your LibreSign verification code.', $code));
		return $this->hasher->hash($code);
	}

	/**
	 * @throws OCSForbiddenException
	 * @return \OCA\TwoFactorGateway\Provider\Gateway\IGateway
	 */
	private function getGateway(string $gatewayName) {
		try {
			$factory = Server::get(\OCA\TwoFactorGateway\Provider\Gateway\Factory::class);
		} catch (NotFoundExceptionInterface) {
			throw new LibresignException('App Two-Factor Gateway is not installed.');

		}
		$gateway = $factory->getGateway($gatewayName);
		if (!$gateway->getConfig()->isComplete()) {
			throw new OCSForbiddenException($this->l10n->t('Gateway %s not configured on Two-Factor Gateway.', $gatewayName));
		}
		return $gateway;
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
