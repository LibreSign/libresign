<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\IdentifyMethod\SignatureMethod;

use OCA\Libresign\Enum\CrlValidationStatus;
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
			$certificateData = $this->pkcs12Handler
				->setCertificate($this->pkcs12Handler->getPfxOfCurrentSigner($this->userSession->getUser()?->getUID()))
				->setPassword($this->codeSentByUser)
				->readCertificate();
		} catch (InvalidPasswordException) {
			throw new LibresignException($this->identifyService->getL10n()->t('Invalid user or password'));
		}

		$this->validateCertificateRevocation($certificateData);
		$this->validateCertificateExpiration($certificateData);
	}

	private function validateCertificateRevocation(array $certificateData): void {
		if (!array_key_exists('crl_validation', $certificateData)) {
			return;
		}
		$status = $certificateData['crl_validation'];
		if ($status === CrlValidationStatus::VALID) {
			return;
		}
		if ($status === CrlValidationStatus::REVOKED) {
			throw new LibresignException($this->identifyService->getL10n()->t('Certificate has been revoked'), 400);
		}
		// Admin explicitly disabled external CRL validation – allow signing.
		if ($status === CrlValidationStatus::DISABLED) {
			return;
		}
		// Any other status (urls_inaccessible, validation_failed, validation_error, etc.):
		// fail-closed – we cannot confirm the certificate is not revoked.
		throw new LibresignException(
			$this->identifyService->getL10n()->t('Certificate revocation status could not be verified'),
			422
		);
	}

	private function validateCertificateExpiration(array $certificateData): void {
		if (array_key_exists('validTo_time_t', $certificateData)) {
			$validTo = $certificateData['validTo_time_t'];
			if (!is_int($validTo)) {
				throw new LibresignException($this->identifyService->getL10n()->t('Invalid certificate'), 422);
			}
			$now = (new \DateTime())->getTimestamp();
			if ($validTo <= $now) {
				throw new LibresignException($this->identifyService->getL10n()->t('Certificate has expired'), 422);
			}
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
