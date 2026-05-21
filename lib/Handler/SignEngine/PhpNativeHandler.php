<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\SignEngine;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\DocMdp\ConfigService as DocMdpConfigService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicy;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicyValue;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureStampPreview\SignatureStampAppearanceBuilder;
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
		private SignatureStampAppearanceBuilder $signatureStampAppearanceBuilder,
		private SignatureBackgroundService $signatureBackgroundService,
		private PolicyService $policyService,
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
					useDefaultAppearance: false,
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
		// GRAPHIC_ONLY: user's drawn image occupies the full bbox in n2; no description text.
		$userImgPath = null;
		$userImgRect = null;
		if ($renderMode === SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION) {
			if ($signatureImagePath !== '' && is_file($signatureImagePath)) {
				$userImgPath = $signatureImagePath;
				$userImgRect = [0.0, 0.0, (float)$width / 2.0, (float)$height];
			}
		} elseif ($renderMode === SignerElementsService::RENDER_MODE_GRAPHIC_ONLY) {
			if ($signatureImagePath !== '' && is_file($signatureImagePath)) {
				$userImgPath = $signatureImagePath;
				$userImgRect = null; // full bbox
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
		$tsaSettings = $this->getTsaSettings();
		$tsaUrl = $tsaSettings['url'];
		if (empty($tsaUrl)) {
			return null;
		}

		$username = null;
		$password = null;
		$authType = $tsaSettings['auth_type'];
		if ($authType === 'basic') {
			$username = $tsaSettings['username'] ?: null;
			$password = $this->appConfig->getValueString(Application::APP_ID, TsaPolicy::PASSWORD_APP_CONFIG_KEY, '') ?: null;
		}

		return new TimestampOptionsDto(
			tsaUrl: $tsaUrl,
			username: $username,
			password: $password,
		);
	}

	/**
	 * @return array{url: string, policy_oid: string, auth_type: string, username: string}
	 */
	private function getTsaSettings(): array {
		$resolved = $this->policyService->resolve(TsaPolicy::KEY)->getEffectiveValue();
		$settings = TsaPolicyValue::decode($resolved);
		return $settings;
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
		$fallbackCommonName = $this->readCertificate()['subject']['CN'] ?? null;

		return $this->signatureStampAppearanceBuilder->buildXObject(
			width: $width,
			height: $height,
			renderMode: $renderMode,
			context: $params,
			fallbackCommonName: is_string($fallbackCommonName) ? $fallbackCommonName : null,
		);
	}

	/**
	 * @return string[]
	 */
	private function wrapTextForPdf(string $line, float $availableWidth, float $fontSize): array {
		return $this->signatureStampAppearanceBuilder->wrapTextForPdf($line, $availableWidth, $fontSize);
	}

	private function escapePdfText(string $value): string {
		return $this->signatureStampAppearanceBuilder->escapePdfText($value);
	}
}
