<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\TwofactorGatewayService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IL10N;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;

class TokenService {
	public const TOKEN_LENGTH = 6;

	public function __construct(
		private ISecureRandom $secureRandom,
		private IHasher $hasher,
		private MailService $mail,
		private IL10N $l10n,
		private TwofactorGatewayService $twofactorGatewayService,
	) {
	}

	public function sendCodeByGateway(string $identifier, string $gatewayName): string {
		$this->twofactorGatewayService->ensureAvailable($gatewayName);
		if (!$this->twofactorGatewayService->isGatewayComplete($gatewayName)) {
			throw new OCSForbiddenException($this->l10n->t('Gateway %s not configured on Two-Factor Gateway.', $gatewayName));
		}

		$code = $this->secureRandom->generate(self::TOKEN_LENGTH, ISecureRandom::CHAR_DIGITS);
		$this->twofactorGatewayService->send(
			$gatewayName,
			$identifier,
			$this->l10n->t('%s is your LibreSign verification code.', $code)
		);
		return $this->hasher->hash($code);
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
