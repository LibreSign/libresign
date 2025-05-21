<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use Exception;
use Imagick;
use ImagickPixel;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Files\TSimpleFile;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\InMemoryFile;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\ITempManager;

class SignatureBackgroundService {
	use TSimpleFile;
	public const SCALE_FACTOR = 3;

	public function __construct(
		private IAppData $appData,
		private IAppConfig $appConfig,
		private IConfig $config,
		private ITempManager $tempManager,
	) {
	}

	private function getRootFolder(): ISimpleFolder {
		try {
			return $this->appData->getFolder('signature');
		} catch (NotFoundException) {
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

		$this->appConfig->setValueString(Application::APP_ID, 'signature_background_type', 'custom');
		$target = $folder->newFile('background.png');
		$target->putContent($content);
	}

	public function getSignatureBackgroundType(): string {
		return $this->appConfig->getValueString(Application::APP_ID, 'signature_background_type', 'default');
	}

	public function isEnabled(): bool {
		return $this->getSignatureBackgroundType() !== 'deleted';
	}

	private function optmizeImage(string $content, float $opacity = 1): string {
		$image = new Imagick();
		$image->setBackgroundColor(new ImagickPixel('transparent'));
		$image->readImageBlob($content);
		$width = $image->getImageWidth();
		$height = $image->getImageHeight();
		$dimensions = $this->scaleDimensions($width, $height);
		if ($dimensions['width'] === $width && $dimensions['height'] === $height && $image->readImageBlob($content) === 'PNG') {
			return $content;
		}
		$image->setImageResolution(300, 300);
		$image->resampleImage(300, 300, Imagick::FILTER_LANCZOS, 1);
		$image->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
		$image->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity, Imagick::CHANNEL_ALPHA);
		$image->setImageFormat('png');
		$image->resizeImage($dimensions['width'], $dimensions['height'], Imagick::FILTER_LANCZOS, 1);
		return $image->getImageBlob();
	}

	private function scaleDimensions(int $width, int $height): array {
		$signatureWidth = $this->appConfig->getValueFloat(Application::APP_ID, 'signature_width', SignatureTextService::DEFAULT_SIGNATURE_WIDTH);
		$signatureHeight = $this->appConfig->getValueFloat(Application::APP_ID, 'signature_height', SignatureTextService::DEFAULT_SIGNATURE_HEIGHT);

		$maxWidth = $signatureWidth * self::SCALE_FACTOR;
		$maxHeight = $signatureHeight * self::SCALE_FACTOR;

		if ($width <= $maxWidth && $height <= $maxHeight) {
			return ['width' => $width, 'height' => $height];
		}

		$widthRatio = $maxWidth / $width;
		$heightRatio = $maxHeight / $height;
		$scale = min($widthRatio, $heightRatio);

		$returnWidth = (int)floor($width * $scale);
		$returnHeight = (int)floor($height * $scale);

		return ['width' => $returnWidth, 'height' => $returnHeight];
	}

	public function delete(): void {
		try {
			$this->appConfig->setValueString(Application::APP_ID, 'signature_background_type', 'deleted');
			$file = $this->getRootFolder()->getFile('background.png');
			$file->delete();
		} catch (NotFoundException|NotPermittedException) {
		}
	}

	public function reset(): void {
		try {
			$this->appConfig->deleteKey(Application::APP_ID, 'signature_background_type');
			$file = $this->getRootFolder()->getFile('background.png');
			$file->delete();
		} catch (NotFoundException|NotPermittedException) {
		}
	}

	public function getImage(): ISimpleFile {
		try {
			$file = $this->getRootFolder()->getFile('background.png');
		} catch (NotFoundException) {
			$content = $this->optmizeImage(file_get_contents(__DIR__ . '/../../img/logo-gray.svg'), 0.15);
			$file = new InMemoryFile('background.png', $content);
		}
		return $file;
	}

	public function getImagePath(): string {
		try {
			$filePath = $this->getRootFolder()->getFile('background.png');
			$dataDir = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data/');
			return $dataDir . '/' . $this->getInternalPathOfFile($filePath);
		} catch (NotFoundException) {
			$content = $this->optmizeImage(file_get_contents(__DIR__ . '/../../img/logo-gray.svg'), 0.3);
			$filePath = $this->tempManager->getTemporaryFile('.png');
			if (!$filePath) {
				throw new Exception('Imposible to write temporary file at temporary folder');
			}
			file_put_contents($filePath, $content);
		}
		return $filePath;
	}
}
