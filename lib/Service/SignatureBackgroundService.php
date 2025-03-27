<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;

class SignatureBackgroundService {
	public function __construct(
		private IAppData $appData,
		private IAppConfig $appConfig,
	) {
	}

	private function getRootFolder(): ISimpleFolder {
		try {
			return $this->appData->getFolder('signature');
		} catch (NotFoundException $e) {
			return $this->appData->newFolder('signature');
		}
	}
	public function updateImage(string $tmpFile): void {
		$folder = $this->getRootFolder();
		$detectedMimeType = mime_content_type($tmpFile);
		if (!in_array($detectedMimeType, ['image/png'], true)) {
			throw new \Exception('Unsupported image type: ' . $detectedMimeType);
		}
		$this->appConfig->setAppValueString('signature_background_type', 'custom');
		$target = $folder->newFile('background.png');
		$target->putContent(file_get_contents($tmpFile));
	}

	public function delete(): void {
		try {
			$this->appConfig->deleteAppValue('signature_background_type');
			$file = $this->getRootFolder()->getFile('background.png');
			$file->delete();
		} catch (NotFoundException $e) {
		} catch (NotPermittedException $e) {
		}
	}

	public function reset(): void {
		try {
			$this->appConfig->setAppValueString('signature_background_type', 'default');
			$file = $this->getRootFolder()->getFile('background.png');
			$file->delete();
		} catch (NotFoundException $e) {
		} catch (NotPermittedException $e) {
		}
	}

	public function getImage(): ISimpleFile {
		return $this->getRootFolder()->getFile('background.png');
	}
}
