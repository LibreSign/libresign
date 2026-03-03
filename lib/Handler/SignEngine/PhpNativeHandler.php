<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use Imagick;
use ImagickPixel;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\DocMdp\ConfigService as DocMdpConfigService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\ITempManager;
use SignerPHP\Application\DTO\CertificateCredentialsDto;
use SignerPHP\Application\DTO\CertificationLevel;
use SignerPHP\Application\DTO\PdfContentDto;
use SignerPHP\Application\DTO\SignatureActorDto;
use SignerPHP\Application\DTO\SignatureAppearanceDto;
use SignerPHP\Application\DTO\SignatureMetadataDto;
use SignerPHP\Application\DTO\SigningOptionsDto;
use SignerPHP\Application\DTO\SignPdfRequestDto;
use SignerPHP\Application\DTO\TimestampOptionsDto;
use SignerPHP\Application\Service\PdfSigningService;
use SignerPHP\Infrastructure\Legacy\OpenSslCertificateValidator;
use SignerPHP\Infrastructure\Native\NativePdfSigningEngine;

class PhpNativeHandler extends Pkcs12Handler {
	private const SCALE_FACTOR_MIN = 5;

	public function __construct(
		private IAppConfig $appConfig,
		private DocMdpConfigService $docMdpConfigService,
		private SignatureBackgroundService $signatureBackgroundService,
		private SignatureTextService $signatureTextService,
		private ITempManager $tempManager,
		protected CertificateEngineFactory $certificateEngineFactory,
	) {
	}

	#[\Override]
	public function sign(): File {
		$this->beforeSign();
		$signedContent = $this->getSignedContent();
		$this->getInputFile()->putContent($signedContent);
		return $this->getInputFile();
	}

	#[\Override]
	public function getSignedContent(): string {
		$pdfContent = $this->getInputFile()->getContent();
		$certificate = CertificateCredentialsDto::fromContent(
			$this->getCertificate(),
			$this->getPassword(),
		);
		$service = new PdfSigningService(
			new OpenSslCertificateValidator(),
			new NativePdfSigningEngine(),
		);

		$visibleElements = $this->getVisibleElements();
		$metadata = $this->buildMetadata();
		$timestamp = $this->buildTimestampOptions();
		$certificationLevel = $this->resolveCertificationLevel(empty($visibleElements));

		if (empty($visibleElements)) {
			return $service->sign(SignPdfRequestDto::fromRequired(
				new PdfContentDto($pdfContent),
				$certificate,
				new SigningOptionsDto(
					metadata: $metadata,
					timestamp: $timestamp,
					certificationLevel: $certificationLevel,
				),
			));
		}

		$applyOnce = $certificationLevel;
		// signer-php expects screen/top-left coords (Y=0 at top, grows downward).
		// LibreSign stores PDF bottom-left coords (Y=0 at bottom, lly < ury).
		// Conversion: screen_y = pageHeight - pdf_y
		// Page dimensions come from FileEntity::getMetadata()['d'] (0-based array of ['w','h']).
		$pageDimensions = $this->getSignatureParams()['PageDimensions'] ?? [];
		foreach ($visibleElements as $element) {
			$fileElement = $element->getFileElement();
			$llx = (float)($fileElement->getLlx() ?? 0);
			$lly = (float)($fileElement->getLly() ?? 0);
			$urx = (float)($fileElement->getUrx() ?? 0);
			$ury = (float)($fileElement->getUry() ?? 0);
			$width = (int)($urx - $llx);
			$height = (int)($ury - $lly);
			// signer-php uses 0-based page index; LibreSign stores 1-based
			$pageIndex = max(0, $fileElement->getPage() - 1);
			$pageHeight = (float)$pageDimensions[$pageIndex]['h'];
			$imagePath = $this->prepareVisualImage($element->getTempFile(), $width, $height);
			$appearance = new SignatureAppearanceDto(
				imagePath: $imagePath !== '' ? $imagePath : null,
				rect: [
					$llx,
					$pageHeight - $ury,  // screen top = pageH - PDF ury
					$urx,
					$pageHeight - $lly,  // screen bottom = pageH - PDF lly
				],
				page: $pageIndex,
			);
			$pdfContent = $service->sign(SignPdfRequestDto::fromRequired(
				new PdfContentDto($pdfContent),
				$certificate,
				new SigningOptionsDto(
					metadata: $metadata,
					appearance: $appearance,
					timestamp: $timestamp,
					// DocMDP only applies once (the first signature certifies)
					certificationLevel: $applyOnce,
				),
			));
			$applyOnce = null;
		}

		return $pdfContent;
	}

	#[\Override]
	public function readCertificate(): array {
		$result = $this->certificateEngineFactory
			->getEngine()
			->readCertificate(
				$this->getCertificate(),
				$this->getPassword()
			);

		if (!is_array($result)) {
			throw new \RuntimeException('Failed to read certificate data');
		}

		return $result;
	}

	private function buildMetadata(): SignatureMetadataDto {
		$params = $this->getSignatureParams();
		$name = !empty($params['SignerCommonName']) ? (string)$params['SignerCommonName'] : null;
		$email = !empty($params['SignerEmail']) ? (string)$params['SignerEmail'] : null;

		return new SignatureMetadataDto(
			actor: ($name !== null || $email !== null)
				? new SignatureActorDto(name: $name, contactInfo: $email)
				: null,
		);
	}

	private function buildTimestampOptions(): ?TimestampOptionsDto {
		$tsaUrl = $this->appConfig->getValueString(Application::APP_ID, 'tsa_url', '');
		if (empty($tsaUrl)) {
			return null;
		}

		$username = null;
		$password = null;
		$authType = $this->appConfig->getValueString(Application::APP_ID, 'tsa_auth_type', 'none');
		if ($authType === 'basic') {
			$username = $this->appConfig->getValueString(Application::APP_ID, 'tsa_username', '') ?: null;
			$password = $this->appConfig->getValueString(Application::APP_ID, 'tsa_password', '') ?: null;
		}

		return new TimestampOptionsDto(
			tsaUrl: $tsaUrl,
			username: $username,
			password: $password,
		);
	}

	private function resolveCertificationLevel(bool $noVisibleElements): ?CertificationLevel {
		if (!$this->docMdpConfigService->isEnabled()) {
			return null;
		}

		// DocMDP values mirror CertificationLevel: 1=NoChanges, 2=FormFilling, 3=FormFillAndAnnotations
		$level = $this->docMdpConfigService->getLevel()->value;
		// Only certify on invisible signatures or on the first visible element
		if ($noVisibleElements || !$this->hasExistingSignatures($this->getInputFile()->getContent())) {
			return CertificationLevel::fromInt($level);
		}

		return null;
	}

	/**
	 * Prepare the visual image for a signature appearance.
	 *
	 * Mirrors JSignPdfHandler render-mode logic:
	 * - GRAPHIC_ONLY / default: merge background + user signature
	 * - SIGNAME_AND_DESCRIPTION: merge background + text image of signer name
	 * - No background: use the raw user signature (or text image)
	 */
	private function prepareVisualImage(string $signatureImagePath, int $width, int $height): string {
		$backgroundType = $this->signatureBackgroundService->getSignatureBackgroundType();
		$renderMode = $this->signatureTextService->getRenderMode();

		if ($backgroundType === 'deleted') {
			// No background configured
			if ($renderMode === SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION) {
				return $this->createTextImage($width, $height);
			}
			return $signatureImagePath;
		}

		$backgroundPath = $this->signatureBackgroundService->getImagePath();
		if ($backgroundPath === '') {
			return $signatureImagePath;
		}

		if ($renderMode === SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION) {
			$textImagePath = $this->createTextImage((int)($width / 2), $height);
			return $this->mergeImages($backgroundPath, $textImagePath, $width, $height);
		}

		// GRAPHIC_AND_DESCRIPTION or GRAPHIC_ONLY: background + signature image
		return $this->mergeImages($backgroundPath, $signatureImagePath, $width, $height);
	}

	private function createTextImage(int $width, int $height): string {
		$params = $this->getSignatureParams();
		if (!empty($params['SignerCommonName'])) {
			$commonName = (string)$params['SignerCommonName'];
		} else {
			$certificateData = $this->readCertificate();
			$commonName = $certificateData['subject']['CN'] ?? throw new \RuntimeException('Certificate must have a Common Name (CN) in subject field');
		}

		$content = $this->signatureTextService->signerNameImage(
			text: $commonName,
			width: $width,
			height: $height,
			scale: self::SCALE_FACTOR_MIN,
		);

		$tmpPath = $this->tempManager->getTemporaryFile('_text_image.png');
		if (!$tmpPath) {
			throw new \Exception('Temporary file not accessible');
		}
		file_put_contents($tmpPath, $content);
		return $tmpPath;
	}

	private function mergeImages(string $backgroundPath, string $overlayPath, int $width, int $height): string {
		if (!extension_loaded('imagick')) {
			// Graceful fallback: use overlay directly
			return $overlayPath;
		}

		$background = new Imagick($backgroundPath);
		$overlay = new Imagick($overlayPath);

		$background->setImageFormat('png');
		$overlay->setImageFormat('png');
		$background->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
		$overlay->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);

		$background->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1, true);
		$overlay->resizeImage(
			(int)round($overlay->getImageWidth() * ($width / max($overlay->getImageWidth(), 1))),
			(int)round($overlay->getImageHeight() * ($height / max($overlay->getImageHeight(), 1))),
			Imagick::FILTER_LANCZOS,
			1,
		);

		$canvas = new Imagick();
		$canvas->newImage($width, $height, new ImagickPixel('transparent'));
		$canvas->setImageFormat('png32');
		$canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);

		$bgX = (int)(($width - $background->getImageWidth()) / 2);
		$bgY = (int)(($height - $background->getImageHeight()) / 2);
		$canvas->compositeImage($background, Imagick::COMPOSITE_OVER, $bgX, $bgY);

		$sigX = (int)(($width - $overlay->getImageWidth()) / 2);
		$sigY = (int)(($height - $overlay->getImageHeight()) / 2);
		$canvas->compositeImage($overlay, Imagick::COMPOSITE_OVER, $sigX, $sigY);

		$tmpPath = $this->tempManager->getTemporaryFile('_merged.png');
		if (!$tmpPath) {
			throw new \Exception('Temporary file not accessible');
		}
		$canvas->writeImage($tmpPath);

		$canvas->clear();
		$background->clear();
		$overlay->clear();

		return $tmpPath;
	}

	private function hasExistingSignatures(string $pdfContent): bool {
		return (bool)preg_match('/\/ByteRange\s*\[|\/Type\s*\/Sig\b|\/DocMDP\b|\/Perms\b/', $pdfContent);
	}
}
