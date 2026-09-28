<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use InvalidArgumentException;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Exception\InvalidPasswordException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\SignEngine\Pkcs12Handler;
use OCA\Libresign\Helper\FileUploadHelper;
use OCA\Libresign\Service\Crl\CrlService;
use OCP\Files\IMimeTypeDetector;
use OCP\IL10N;
use OCP\IUser;

class AccountCertificateService {
	public function __construct(
		private IL10N $l10n,
		private CertificateEngineFactory $certificateEngineFactory,
		private Pkcs12Handler $pkcs12Handler,
		private FileUploadHelper $uploadHelper,
		private IMimeTypeDetector $mimeTypeDetector,
		private CrlService $crlService,
	) {
	}

	public function validateCertificateData(array $data): void {
		if (array_key_exists('email', $data['user']) && empty($data['user']['email'])) {
			// TRANSLATORS Account setup error when the user must have an email address in their Nextcloud profile before continuing.
			throw new LibresignException($this->l10n->t('You must have an email. You can define the email in your profile.'), 1);
		}
		if (!empty($data['user']['email']) && !filter_var($data['user']['email'], FILTER_VALIDATE_EMAIL)) {
			// TRANSLATORS Account setup error when the email provided to create or update a signing account is invalid.
			throw new LibresignException($this->l10n->t('Invalid email'), 1);
		}
		if (empty($data['signPassword'])) {
			// TRANSLATORS Account setup error when the dedicated password used to unlock the signing certificate is missing.
			throw new LibresignException($this->l10n->t('Password to sign is mandatory'), 1);
		}
	}

	public function getCertificateEngineName(): string {
		return $this->certificateEngineFactory->getEngine()->getName();
	}

	public function isSetupOk(): bool {
		return $this->certificateEngineFactory->getEngine()->isSetupOk();
	}

	/**
	 * @throws LibresignException at savePfx
	 * @throws InvalidArgumentException
	 */
	public function uploadPfx(array $file, IUser $user): void {
		try {
			$this->uploadHelper->validateUploadedFile($file);
		} catch (InvalidArgumentException) {
			// TRANSLATORS Error when the uploaded certificate file is not valid
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided. Need to be a .pfx file.'));
		}

		if ($file['size'] > 10 * 1024) {
			// TRANSLATORS Error when the certificate file is bigger than normal
			throw new InvalidArgumentException($this->l10n->t('File is too big'));
		}
		$content = file_get_contents($file['tmp_name']);
		$mimetype = $this->mimeTypeDetector->detectString($content);
		if ($mimetype !== 'application/octet-stream') {
			// TRANSLATORS Error when the mimetype of uploaded file is not valid
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided. Need to be a .pfx file.'));
		}
		$extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
		if ($extension !== 'pfx') {
			// TRANSLATORS Error when the certificate file is not a pfx file
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided. Need to be a .pfx file.'));
		}
		unlink($file['tmp_name']);
		$this->pkcs12Handler->savePfx($user->getUID(), $content);
	}

	public function deletePfx(IUser $user): void {
		$uid = $user->getUID();

		$this->crlService->revokeUserCertificates(
			$uid,
			CRLReason::CESSATION_OF_OPERATION,
			'Certificate deleted by account owner.',
			$uid,
		);

		$this->pkcs12Handler->deletePfx($uid);
	}

	/**
	 * @throws LibresignException when have not a certificate file
	 */
	public function updatePfxPassword(IUser $user, string $current, string $new): void {
		try {
			$pfx = $this->pkcs12Handler->updatePassword($user->getUID(), $current, $new);
		} catch (InvalidPasswordException) {
			// TRANSLATORS Authentication error shown when the account credentials used to access LibreSign are invalid.
			throw new LibresignException($this->l10n->t('Invalid user or password'));
		}
	}

	/**
	 * @throws LibresignException when have not a certificate file
	 */
	public function readPfxData(IUser $user, string $password): array {
		try {
			return $this->pkcs12Handler
				->setCertificate($this->pkcs12Handler->getPfxOfCurrentSigner($user->getUID()))
				->setPassword($password)
				->readCertificate();
		} catch (InvalidPasswordException) {
			// TRANSLATORS Authentication error shown when the account credentials used to access LibreSign are invalid.
			throw new LibresignException($this->l10n->t('Invalid user or password'));
		}
	}

	public function hasSignatureFile(?IUser $user = null): bool {
		if (!$user) {
			return false;
		}
		try {
			$this->pkcs12Handler->getPfxOfCurrentSigner($user->getUID());
			return true;
		} catch (LibresignException) {
			return false;
		}
	}

	public function createForUser(IUser $user, string $signPassword): void {
		$certificate = $this->pkcs12Handler->generateCertificate(
			[
				'host' => $user->getPrimaryEMailAddress(),
				'uid' => 'account:' . $user->getUID(),
				'name' => $user->getDisplayName()
			],
			$signPassword,
			$user->getDisplayName()
		);
		$this->pkcs12Handler->savePfx($user->getPrimaryEMailAddress(), $certificate);
	}
}
