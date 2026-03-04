<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\DocMdp\ConfigService as DocMdpConfigService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\Files\File;
use OCP\IAppConfig;
use SignerPHP\Application\DTO\CertificateCredentialsDto;
use SignerPHP\Application\DTO\CertificationLevel;
use SignerPHP\Application\DTO\PdfContentDto;
use SignerPHP\Application\DTO\SignatureActorDto;
use SignerPHP\Application\DTO\SignatureAppearanceDto;
use SignerPHP\Application\DTO\SignatureAppearanceXObjectDto;
use SignerPHP\Application\DTO\SignatureMetadataDto;
use SignerPHP\Application\DTO\SigningOptionsDto;
use SignerPHP\Application\DTO\SignPdfRequestDto;
use SignerPHP\Application\DTO\TimestampOptionsDto;
use SignerPHP\Application\Service\PdfSigningService;
use SignerPHP\Infrastructure\Legacy\OpenSslCertificateValidator;
use SignerPHP\Infrastructure\Native\NativePdfSigningEngine;

class PhpNativeHandler extends Pkcs12Handler {
	public function __construct(
		private IAppConfig $appConfig,
		private DocMdpConfigService $docMdpConfigService,
		private SignatureTextService $signatureTextService,
		private SignatureBackgroundService $signatureBackgroundService,
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
			$pageHeight = $this->resolvePageHeight($pageDimensions, $pageIndex);
			$appearance = $this->buildAppearanceForElement(
				llx: $llx,
				lly: $lly,
				urx: $urx,
				ury: $ury,
				pageHeight: $pageHeight,
				pageIndex: $pageIndex,
				width: $width,
				height: $height,
				signatureImagePath: $element->getTempFile(),
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

	private function buildAppearanceForElement(
		float $llx,
		float $lly,
		float $urx,
		float $ury,
		float $pageHeight,
		int $pageIndex,
		int $width,
		int $height,
		string $signatureImagePath = '',
	): SignatureAppearanceDto {
		$renderMode = $this->signatureTextService->getRenderMode();

		// n0 layer: background stamp is always placed full-bbox when enabled.
		$imagePath = $this->signatureBackgroundService->isEnabled()
			? $this->signatureBackgroundService->getImagePath()
			: null;

		// GRAPHIC_AND_DESCRIPTION: user's drawn image goes into the n2 xObject layer
		// on the left half of the bbox so it does not distort or cover the description text.
		// Background (if enabled) still occupies the full n0 layer behind everything.
		$userImgPath = null;
		$userImgRect = null;
		if ($renderMode === SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION) {
			if ($signatureImagePath !== '' && is_file($signatureImagePath)) {
				$userImgPath = $signatureImagePath;
				$userImgRect = [0.0, 0.0, (float)$width / 2.0, (float)$height];
			}
		}

		return new SignatureAppearanceDto(
			backgroundImagePath: $imagePath,
			rect: [
				$llx,
				$pageHeight - $ury,  // screen top = pageH - PDF ury
				$urx,
				$pageHeight - $lly,  // screen bottom = pageH - PDF lly
			],
			page: $pageIndex,
			xObject: $this->buildXObject($width, $height, $renderMode),
			signatureImagePath: $userImgPath,
			signatureImageFrame: $userImgRect,
		);
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

	private function resolvePageHeight(array $pageDimensions, int $pageIndex): float {
		$pageHeight = $pageDimensions[$pageIndex]['h'] ?? null;
		if (!is_numeric($pageHeight) || (float)$pageHeight <= 0.0) {
			throw new \RuntimeException(sprintf('Missing or invalid PageDimensions for page index %d.', $pageIndex));
		}
		return (float)$pageHeight;
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

	private function hasExistingSignatures(string $pdfContent): bool {
		return (bool)preg_match('/\/ByteRange\s*\[|\/Type\s*\/Sig\b|\/DocMDP\b|\/Perms\b/', $pdfContent);
	}

	/**
	 * Builds the xObject (n2 layer) for all render modes using only PDF text operators.
	 *
	 * DESCRIPTION_ONLY      → description text, full width.
	 * GRAPHIC_AND_DESCRIPTION → description text, right half only
	 *                           (user image is in imagePath/n0, handled natively by signer-php).
	 * SIGNAME_AND_DESCRIPTION → signer name as large text on the left half
	 *                           + description text on the right half.
	 *                           No image generation: pure PDF text operators.
	 */
	private function buildXObject(int $width, int $height, string $renderMode): SignatureAppearanceXObjectDto {
		$params = $this->getSignatureParams();
		$serverTimezone = new \DateTimeZone(date_default_timezone_get());
		$now = new \DateTime('now', $serverTimezone);
		$params['ServerSignatureDate'] = $now->format('Y.m.d H:i:s \U\T\C');

		$textData = $this->signatureTextService->parse(context: $params);
		$parsed = trim((string)($textData['parsed'] ?? ''));

		$descFontSize = (float)($textData['templateFontSize'] ?? $this->signatureTextService->getTemplateFontSize());
		$descLineHeight = $descFontSize * 1.2;
		$leftPadding = max(2.0, $descFontSize * 0.15);

		$isDescriptionOnly = $renderMode === SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY;
		$textStartX = $isDescriptionOnly ? $leftPadding : ((float)$width / 2.0) + $leftPadding;
		$availableWidth = $isDescriptionOnly ? (float)$width : (float)$width / 2.0;

		$stream = '';

		// Left half: signer name as large text operators (SIGNAME_AND_DESCRIPTION only).
		// No image generation — the name is drawn directly with PDF text commands.
		if ($renderMode === SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION) {
			$commonName = !empty($params['SignerCommonName'])
				? (string)$params['SignerCommonName']
				: ($this->readCertificate()['subject']['CN'] ?? '');
			if ($commonName !== '') {
				$nameFontSize = $this->signatureTextService->getSignatureFontSize();
				$leftHalfW = (float)$width / 2.0 - $leftPadding * 2;
				$nameLines = $this->wrapTextForPdf($commonName, $leftHalfW, $nameFontSize);
				$nameLineCount = count($nameLines);
				$totalNameHeight = $nameLineCount * $nameFontSize * 1.2;
				$nameStartY = ((float)$height + $totalNameHeight) / 2.0 - $nameFontSize;
				$nameStartY = max(0.0, $nameStartY);
				$nameY = $nameStartY;
				foreach ($nameLines as $nameLine) {
					$escaped = $this->escapePdfText($nameLine);
					$stream .= "BT\n";
					$stream .= sprintf("/F1 %.2F Tf\n", $nameFontSize);
					$stream .= "0 0 0 rg\n";
					$stream .= sprintf("%.2F %.2F Td\n", $leftPadding, $nameY);
					$stream .= sprintf("(%s) Tj\n", $escaped);
					$stream .= "ET\n";
					$nameY -= $nameFontSize * 1.2;
				}
			}
		}

		// Right half (or full width): description text.
		$currentY = (float)$height - $descFontSize - 2.0;
		foreach (explode(PHP_EOL, $parsed) as $line) {
			$wrappedLines = $this->wrapTextForPdf($line, $availableWidth, $descFontSize);
			foreach ($wrappedLines as $wrappedLine) {
				if ($currentY < 0) {
					break 2;
				}
				$escaped = $this->escapePdfText($wrappedLine);
				$stream .= "BT\n";
				$stream .= sprintf("/F1 %.2F Tf\n", $descFontSize);
				$stream .= "0 0 0 rg\n";
				$stream .= sprintf("%.2F %.2F Td\n", $textStartX, $currentY);
				$stream .= sprintf("(%s) Tj\n", $escaped);
				$stream .= "ET\n";
				$currentY -= $descLineHeight;
			}
		}

		return new SignatureAppearanceXObjectDto(
			stream: $stream,
			resources: [
				'Font' => [
					'F1' => [
						'Type' => '/Font',
						'Subtype' => '/Type1',
						'BaseFont' => '/Helvetica',
					],
				],
			],
		);
	}

	/**
	 * @return string[]
	 */
	private function wrapTextForPdf(string $line, float $availableWidth, float $fontSize): array {
		$trimmed = trim($line);
		if ($trimmed === '') {
			return [''];
		}

		$estimatedCharWidth = max(1.0, $fontSize * 0.52);
		$maxChars = max(1, (int)floor($availableWidth / $estimatedCharWidth));
		if (strlen($trimmed) <= $maxChars) {
			return [$trimmed];
		}

		$result = [];
		$current = '';
		foreach (preg_split('/\s+/', $trimmed) ?: [] as $word) {
			if ($word === '') {
				continue;
			}

			$candidate = $current === '' ? $word : $current . ' ' . $word;
			if (strlen($candidate) <= $maxChars) {
				$current = $candidate;
				continue;
			}

			if ($current !== '') {
				$result[] = $current;
				$current = '';
			}

			while (strlen($word) > $maxChars) {
				$result[] = substr($word, 0, $maxChars);
				$word = substr($word, $maxChars);
			}

			$current = $word;
		}

		if ($current !== '') {
			$result[] = $current;
		}

		return $result;
	}

	private function escapePdfText(string $value): string {
		$value = str_replace('\\', '\\\\', $value);
		$value = str_replace('(', '\\(', $value);
		$value = str_replace(')', '\\)', $value);

		return $value;
	}
}
