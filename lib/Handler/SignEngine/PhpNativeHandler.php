<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use Imagick;
use ImagickDraw;
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
	 * - GRAPHIC_AND_DESCRIPTION (default): left=signature drawing, right=description text, bg=watermark
	 * - SIGNAME_AND_DESCRIPTION: left=signer-name image, right=description text, bg=watermark
	 * - GRAPHIC_ONLY: full area = signature drawing + watermark background, no text
	 */
	private function prepareVisualImage(string $signatureImagePath, int $width, int $height): string {
		$backgroundType = $this->signatureBackgroundService->getSignatureBackgroundType();
		$renderMode = $this->signatureTextService->getRenderMode();
		$hasBackground = $backgroundType !== 'deleted';
		$backgroundPath = $hasBackground ? $this->signatureBackgroundService->getImagePath() : '';
		$isGraphicOnly = $renderMode === SignerElementsService::RENDER_MODE_GRAPHIC_ONLY;

		if ($isGraphicOnly) {
			if (!$hasBackground || $backgroundPath === '') {
				return $signatureImagePath;
			}
			return $this->composeGraphicOnly($backgroundPath, $signatureImagePath, $width, $height);
		}

		// GRAPHIC_AND_DESCRIPTION or SIGNAME_AND_DESCRIPTION:
		// left half = image, right half = description text from template
		$halfWidth = (int)($width / 2);
		$leftImagePath = ($renderMode === SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION)
			? $this->createSignerNameImage($halfWidth, $height)
			: $signatureImagePath;

		$descriptionImagePath = $this->createDescriptionImage($halfWidth, $height);

		return $this->composeFullAppearance(
			$backgroundPath !== '' ? $backgroundPath : null,
			$leftImagePath,
			$descriptionImagePath,
			$width,
			$height,
		);
	}

	/**
	 * Generates a PNG image of the signer's common name (for SIGNAME_AND_DESCRIPTION left panel).
	 */
	private function createSignerNameImage(int $width, int $height): string {
		$params = $this->getSignatureParams();
		$commonName = !empty($params['SignerCommonName'])
			? (string)$params['SignerCommonName']
			: ($this->readCertificate()['subject']['CN'] ?? throw new \RuntimeException('Certificate must have a CN'));

		$content = $this->signatureTextService->signerNameImage(
			text: $commonName,
			width: $width,
			height: $height,
			align: 'center',
			scale: self::SCALE_FACTOR_MIN,
		);

		$tmpPath = $this->tempManager->getTemporaryFile('_signame.png');
		if (!$tmpPath) {
			throw new \Exception('Temporary file not accessible');
		}
		file_put_contents($tmpPath, $content);
		return $tmpPath;
	}

	/**
	 * Generates a PNG image of the signature description text (for the right panel).
	 * Renders top-left aligned, respecting existing newlines from the template,
	 * without re-wrapping (unlike signerNameImage which vertically centers).
	 */
	private function createDescriptionImage(int $width, int $height): string {
		if (!extension_loaded('imagick')) {
			throw new \Exception('Extension imagick is not loaded.');
		}

		$textData = $this->signatureTextService->parse(context: $this->getSignatureParams());
		$parsed = trim($textData['parsed'] ?? '');
		$fontSize = (float)($textData['templateFontSize'] ?? $this->signatureTextService->getTemplateFontSize());

		$scale = self::SCALE_FACTOR_MIN;
		$canvasW = (int)($width * $scale);
		$canvasH = (int)($height * $scale);
		$scaledFontSize = $fontSize * $scale;

		$image = new Imagick();
		$image->setResolution(600, 600);
		$image->newImage($canvasW, $canvasH, new ImagickPixel('transparent'));
		$image->setImageFormat('png');

		$draw = new ImagickDraw();
		$fonts = Imagick::queryFonts();
		if ($fonts) {
			$draw->setFont($fonts[0]);
		} else {
			$fallback = __DIR__ . '/../../3rdparty/composer/mpdf/mpdf/ttfonts/DejaVuSerifCondensed.ttf';
			if (!file_exists($fallback)) {
				throw new \Exception('No fonts available and fallback font not found: ' . $fallback);
			}
			$draw->setFont($fallback);
		}
		$draw->setFontSize($scaledFontSize);
		$draw->setFillColor(new ImagickPixel('black'));
		$draw->setTextAlignment(Imagick::ALIGN_LEFT);

		// Measure one line to get the ascender (baseline offset from top).
		// iText (used by JSignPdf) uses leading = font_size × 1.2, so we mirror
		// that here rather than relying on Imagick's textHeight which varies by font.
		$metrics = $image->queryFontMetrics($draw, 'Ag');
		$lineHeight = $scaledFontSize;
		$ascender = $metrics['ascender'];

		// Top padding: ~35% of lineHeight — balances the space before first baseline
		$topPadding = $lineHeight * 0.35;
		$leftPadding = (int)($scaledFontSize * 0.1);
		$x = $leftPadding;
		$y = $ascender + $topPadding;

		foreach (explode("\n", $parsed) as $line) {
			if ($y > $canvasH) {
				break;
			}
			$image->annotateImage($draw, $x, $y, 0, $line);
			$y += $lineHeight;
		}

		$blob = $image->getImagesBlob();
		$image->destroy();
		$draw->destroy();

		$tmpPath = $this->tempManager->getTemporaryFile('_description.png');
		if (!$tmpPath) {
			throw new \Exception('Temporary file not accessible');
		}
		file_put_contents($tmpPath, $blob);
		return $tmpPath;
	}

	/**
	 * Composes: watermark background over the full canvas,
	 * left image on the left half, description image on the right half.
	 * Output is at SCALE_FACTOR_MIN × the annotation dimensions for good resolution.
	 */
	private function composeFullAppearance(?string $backgroundPath, string $leftImagePath, string $rightImagePath, int $width, int $height): string {
		if (!extension_loaded('imagick')) {
			return $leftImagePath;
		}

		$scale = self::SCALE_FACTOR_MIN;
		$canvasW = $width * $scale;
		$canvasH = $height * $scale;
		$halfW = (int)($canvasW / 2);

		$canvas = new Imagick();
		$canvas->newImage($canvasW, $canvasH, new ImagickPixel('transparent'));
		$canvas->setImageFormat('png32');
		$canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);

		// 1. Background watermark spanning the full canvas (preserve aspect ratio, centered)
		if ($backgroundPath !== null && $backgroundPath !== '') {
			$bg = new Imagick($backgroundPath);
			$bg->setImageFormat('png');
			$bg->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
			$bg->resizeImage($canvasW, $canvasH, Imagick::FILTER_LANCZOS, 1, true);
			$bgX = (int)(($canvasW - $bg->getImageWidth()) / 2);
			$bgY = (int)(($canvasH - $bg->getImageHeight()) / 2);
			$canvas->compositeImage($bg, Imagick::COMPOSITE_OVER, $bgX, $bgY);
			$bg->clear();
		}

		// 2. Left half: signature drawing (fit within half, preserve aspect ratio)
		$left = new Imagick($leftImagePath);
		$left->setImageFormat('png');
		$left->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
		$left->resizeImage($halfW, $canvasH, Imagick::FILTER_LANCZOS, 1, true);
		$leftX = (int)(($halfW - $left->getImageWidth()) / 2);
		$leftY = (int)(($canvasH - $left->getImageHeight()) / 2);
		$canvas->compositeImage($left, Imagick::COMPOSITE_OVER, $leftX, $leftY);
		$left->clear();

		// 3. Right half: description image (already rendered at scale*halfWidth px)
		$right = new Imagick($rightImagePath);
		$right->setImageFormat('png');
		$right->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
		$canvas->compositeImage($right, Imagick::COMPOSITE_OVER, $halfW, 0);
		$right->clear();

		$tmpPath = $this->tempManager->getTemporaryFile('_appearance.png');
		if (!$tmpPath) {
			throw new \Exception('Temporary file not accessible');
		}
		$canvas->writeImage($tmpPath);
		$canvas->clear();

		return $tmpPath;
	}

	/**
	 * Composes: watermark background + signature drawing at full width (GRAPHIC_ONLY mode).
	 */
	private function composeGraphicOnly(string $backgroundPath, string $signatureImagePath, int $width, int $height): string {
		if (!extension_loaded('imagick')) {
			return $signatureImagePath;
		}

		$scale = self::SCALE_FACTOR_MIN;
		$canvasW = $width * $scale;
		$canvasH = $height * $scale;

		$canvas = new Imagick();
		$canvas->newImage($canvasW, $canvasH, new ImagickPixel('transparent'));
		$canvas->setImageFormat('png32');
		$canvas->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);

		$bg = new Imagick($backgroundPath);
		$bg->setImageFormat('png');
		$bg->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
		$bg->resizeImage($canvasW, $canvasH, Imagick::FILTER_LANCZOS, 1, true);
		$bgX = (int)(($canvasW - $bg->getImageWidth()) / 2);
		$bgY = (int)(($canvasH - $bg->getImageHeight()) / 2);
		$canvas->compositeImage($bg, Imagick::COMPOSITE_OVER, $bgX, $bgY);
		$bg->clear();

		$sig = new Imagick($signatureImagePath);
		$sig->setImageFormat('png');
		$sig->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
		$sig->resizeImage($canvasW, $canvasH, Imagick::FILTER_LANCZOS, 1, true);
		$sigX = (int)(($canvasW - $sig->getImageWidth()) / 2);
		$sigY = (int)(($canvasH - $sig->getImageHeight()) / 2);
		$canvas->compositeImage($sig, Imagick::COMPOSITE_OVER, $sigX, $sigY);
		$sig->clear();

		$tmpPath = $this->tempManager->getTemporaryFile('_graphic_only.png');
		if (!$tmpPath) {
			throw new \Exception('Temporary file not accessible');
		}
		$canvas->writeImage($tmpPath);
		$canvas->clear();

		return $tmpPath;
	}

	private function hasExistingSignatures(string $pdfContent): bool {
		return (bool)preg_match('/\/ByteRange\s*\[|\/Type\s*\/Sig\b|\/DocMDP\b|\/Perms\b/', $pdfContent);
	}
}
