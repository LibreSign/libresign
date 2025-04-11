<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;

class CertificatePolicyService {
	public function __construct(
		private IAppData $appData,
	) {
	}

	public function updateFile(string $tmpFile): void {
		$detectedMimeType = mime_content_type($tmpFile);
		if (!in_array($detectedMimeType, ['application/pdf'], true)) {
			throw new \Exception('Unsupported image type: ' . $detectedMimeType);
		}

		$blob = file_get_contents($tmpFile);
		$rootFolder = $this->appData->getFolder('/');
		try {
			$rootFolder->newFile('certificate-policy.pdf', $blob);
		} catch (NotFoundException $e) {
			$file = $rootFolder->getFile('certificate-policy.pdf');
			$file->putContent($blob);
		}
	}

	public function getFile(): ISimpleFile {
		return $this->appData->getFolder('/')->getFile('certificate-policy.pdf');
	}

	public function deleteFile(): void {
		$this->appData->getFolder('/')->getFile('certificate-policy.pdf')->delete();
	}
}
