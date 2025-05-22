<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use UnexpectedValueException;

class CertificatePolicyService {
	public function __construct(
		private IAppData $appData,
		private IURLGenerator $urlGenerator,
		private IAppConfig $appConfig,
		private IL10N $l10n,
	) {
	}

	public function updateFile(string $tmpFile): string {
		$detectedMimeType = mime_content_type($tmpFile);
		if ($detectedMimeType !== 'application/pdf') {
			throw new UnexpectedValueException('Unsupported image type: ' . $detectedMimeType);
		}

		$blob = file_get_contents($tmpFile);
		$rootFolder = $this->appData->getFolder('/');
		try {
			$rootFolder->newFile('certificate-policy.pdf', $blob);
		} catch (NotFoundException) {
			$file = $rootFolder->getFile('certificate-policy.pdf');
			$file->putContent($blob);
		}
		return $this->urlGenerator->linkToRouteAbsolute('libresign.CertificatePolicy.getCertificatePolicy');
	}

	public function getFile(): ISimpleFile {
		return $this->appData->getFolder('/')->getFile('certificate-policy.pdf');
	}

	public function deleteFile(): void {
		try {
			$this->appData->getFolder('/')->getFile('certificate-policy.pdf')->delete();
		} catch (NotFoundException) {
		}
	}

	public function updateOid(string $oid): string {
		if (empty($oid)) {
			$this->appConfig->deleteKey(Application::APP_ID, 'certificate_policies_oid');
			return '';
		}
		$regex = '(0|1|2)(\.\d+)+';
		preg_match('/^' . $regex . '$/', $oid, $matches);
		if (empty($matches)) {
			// TRANSLATORS This message appears when an invalid Object
			// Identifier (OID) is entered. It informs the admin that the input
			// must follow a specific numeric pattern used in digital
			// certificate policies.
			throw new LibresignException($this->l10n->t('Invalid OID format. Expected pattern: %s', [$regex]));
		}
		$this->appConfig->setValueString(Application::APP_ID, 'certificate_policies_oid', $oid);
		return $oid;
	}

	public function getOid(): string {
		return $this->appConfig->getValueString(Application::APP_ID, 'certificate_policies_oid', '');
	}

	public function getCps(): string {
		try {
			$this->getFile();
		} catch (NotFoundException) {
			return '';
		}
		return $this->urlGenerator->linkToRouteAbsolute('libresign.CertificatePolicy.getCertificatePolicy');
	}
}
