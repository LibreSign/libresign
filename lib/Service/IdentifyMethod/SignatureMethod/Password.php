<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCP\IUserSession;

class Password extends AbstractSignatureMethod {
	public function __construct(
		protected IdentifyService $identifyService,
		protected Pkcs12Handler $pkcs12Handler,
		private IUserSession $userSession,
	) {
		// TRANSLATORS Name of possible authenticator method. This signalize that the signer could be identified by certificate password
		$this->friendlyName = $this->identifyService->getL10n()->t('Certificate with password');
		parent::__construct(
			$identifyService,
		);
	}

	#[\Override]
	public function validateToSign(): void {
		$this->validateToIdentify();
		try {
			$this->pkcs12Handler
				->setCertificate($this->pkcs12Handler->getPfxOfCurrentSigner($this->userSession->getUser()?->getUID()))
				->setPassword($this->codeSentByUser)
				->readCertificate();
		} catch (InvalidPasswordException) {
			throw new LibresignException($this->identifyService->getL10n()->t('Invalid user or password'));
		}
	}

	#[\Override]
	public function validateToIdentify(): void {
		$this->pkcs12Handler->setPassword($this->codeSentByUser);
		$pfx = $this->pkcs12Handler->getPfxOfCurrentSigner($this->userSession->getUser()?->getUID());
		if (empty($pfx)) {
			throw new LibresignException($this->identifyService->getL10n()->t('Invalid certificate'));
		}
	}

	#[\Override]
	public function toArray(): array {
		$return = parent::toArray();
		$return['hasSignatureFile'] = $this->hasSignatureFile();
		return $return;
	}

	private function hasSignatureFile(): bool {
		try {
			$this->pkcs12Handler->getPfxOfCurrentSigner($this->userSession->getUser()?->getUID());
			return true;
		} catch (\Throwable) {
		}
		return false;
	}
}
