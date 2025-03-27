<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use Imagick;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;

class SignatureBackgroundService {
	private bool $wasBackgroundScaled = false;
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

		$content = $this->optmizeImage(file_get_contents($tmpFile));

		$this->appConfig->setAppValueString('signature_background_type', 'custom');
		$target = $folder->newFile('background.png');
		$target->putContent($content);
	}

	public function wasBackgroundScaled(): bool {
		return $this->wasBackgroundScaled;
	}

	private function optmizeImage(string $content): string {
		$image = new Imagick();
		$image->readImageBlob($content);
		$width = $image->getImageWidth();
		$height = $image->getImageHeight();
		$dimensions = $this->scaleDimensions($width, $height);
		if ($dimensions['width'] === $width && $dimensions['height'] === $height) {
			return $content;
		}
		$this->wasBackgroundScaled = true;
		$image->setImageFormat('png');
		$image->resizeImage($dimensions['width'], $dimensions['height'], Imagick::FILTER_LANCZOS, 1);
		return $image->getImageBlob();
	}

	private function scaleDimensions(int $width, int $height): array {
		if ($width <= SignerElementsService::ELEMENT_SIGN_WIDTH) {
			if ($height <= SignerElementsService::ELEMENT_SIGN_HEIGHT) {
				return ['width' => $width, 'height' => $height];
			}
		}

		$widthRatio = SignerElementsService::ELEMENT_SIGN_WIDTH / $width;
		$heightRatio = SignerElementsService::ELEMENT_SIGN_HEIGHT / $height;

		$scale = min($widthRatio, $heightRatio);

		$newWidth = (int)floor($width * $scale);
		$newHeight = (int)floor($height * $scale);

		return ['width' => $newWidth, 'height' => $newHeight];
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
