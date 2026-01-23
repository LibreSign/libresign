<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Handler\SignEngine\SignEngineHandler;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Security\Events\GenerateSecurePasswordEvent;
use OCP\Security\ISecureRandom;

class PfxProvider {
	public function __construct(
		private CertificateValidityPolicy $certificateValidityPolicy,
		private IEventDispatcher $eventDispatcher,
		private ISecureRandom $secureRandom,
	) {
	}

	/**
	 * @return array{pfx: string, password: ?string}
	 */
	public function getOrGeneratePfx(
		SignEngineHandler $engine,
		bool $signWithoutPassword,
		?string $signatureMethodName,
		string $userUniqueIdentifier,
		string $friendlyName,
		string $password = '',
	): array {
		if ($certificate = $engine->getCertificate()) {
			return [
				'pfx' => $certificate,
				'password' => $password,
			];
		}

		$effectivePassword = $password;
		if ($signWithoutPassword) {
			$effectivePassword = $this->generateTemporaryPassword();
			$expiryOverride = $this->certificateValidityPolicy->getLeafExpiryDays(
				$signatureMethodName,
				$signWithoutPassword,
			);
			if ($expiryOverride !== null) {
				$engine->setLeafExpiryOverrideInDays($expiryOverride);
			}
			try {
				$engine->generateCertificate(
					[
						'host' => $userUniqueIdentifier,
						'uid' => $userUniqueIdentifier,
						'name' => $friendlyName,
					],
					$effectivePassword,
					$friendlyName,
				);
			} finally {
				if ($expiryOverride !== null) {
					$engine->setLeafExpiryOverrideInDays(null);
				}
			}
		}

		return [
			'pfx' => $engine->getPfxOfCurrentSigner(),
			'password' => $effectivePassword !== '' ? $effectivePassword : null,
		];
	}

	private function generateTemporaryPassword(): string {
		$passwordEvent = new GenerateSecurePasswordEvent();
		$this->eventDispatcher->dispatchTyped($passwordEvent);
		return $passwordEvent->getPassword() ?? $this->secureRandom->generate(20);
	}
}
