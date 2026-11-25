<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureStampPreview;

use Imagick;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicyValue;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Dto\CompileResult;
use OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Pdf\EmbeddedPdfImage;
use OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Pdf\FilesystemPdfImageEmbedder;
use OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Pdf\PdfImageEmbedderInterface;
use OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Pdf\SinglePagePdfExporter;
use OCP\Files\NotFoundException;

class SignatureStampPreviewNativeService {
	private const PREVIEW_DOCUMENT_UUID = '11111111-2222-4333-8444-555555555555';
	private const PREVIEW_ISSUER_COMMON_NAME = 'Preview Issuer';
	private const PREVIEW_SIGNER_EMAIL = 'preview.signer@libresign.test';
	private const PREVIEW_SIGNER_IDENTIFIER = 'preview-signer';
	private const PREVIEW_SIGNER_IP = '192.0.2.10';
	private const PREVIEW_SIGNER_USER_AGENT = 'LibreSign Preview Browser';

	public function __construct(
		private SignatureStampAppearanceBuilder $appearanceBuilder,
		private SignatureTextService $signatureTextService,
		private SignatureBackgroundService $signatureBackgroundService,
	) {
	}

	public function renderPreviewPdf(
		string $template = '',
		float $templateFontSize = SignatureTextPolicyValue::DEFAULT_TEMPLATE_FONT_SIZE,
		float $signatureFontSize = SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
		float $signatureWidth = SignatureTextPolicyValue::DEFAULT_SIGNATURE_WIDTH,
		float $signatureHeight = SignatureTextPolicyValue::DEFAULT_SIGNATURE_HEIGHT,
		string $renderMode = SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION,
		string $backgroundType = 'default',
	): string {
		$width = max(1.0, $signatureWidth);
		$height = max(1.0, $signatureHeight);

		$backgroundType = trim(strtolower($backgroundType));

		$xObject = $this->appearanceBuilder->buildXObject(
			width: (int)round($width),
			height: (int)round($height),
			renderMode: $renderMode,
			context: $this->buildPreviewContext(),
			template: $template,
			templateFontSize: $templateFontSize,
			signatureFontSize: $signatureFontSize,
			fallbackCommonName: $this->signatureTextService->getPreviewSignerName(),
		);

		$backgroundJpeg = $this->resolveBackgroundJpeg($backgroundType);
		$previewSignatureImage = $this->getPreviewSignatureImage($renderMode);

		return $this->buildSinglePagePdf($width, $height, $xObject->stream, $xObject->resources ?? [], $backgroundJpeg, $previewSignatureImage, $renderMode);
	}

	/**
	 * @return array<string, string>
	 */
	private function buildPreviewContext(): array {
		$previewSignerName = $this->signatureTextService->getPreviewSignerName();

		return [
			'DocumentUUID' => self::PREVIEW_DOCUMENT_UUID,
			'IssuerCommonName' => self::PREVIEW_ISSUER_COMMON_NAME,
			'LocalSignerSignatureDateOnly' => '2026-05-20',
			'LocalSignerSignatureDateTime' => '2026-05-20T14:30:00+00:00',
			'LocalSignerTimezone' => 'UTC',
			'ServerSignatureDate' => '2026-05-20T14:30:00+00:00',
			'SignerCommonName' => $previewSignerName,
			'SignerEmail' => self::PREVIEW_SIGNER_EMAIL,
			'SignerIdentifier' => self::PREVIEW_SIGNER_IDENTIFIER,
			'SignerIP' => self::PREVIEW_SIGNER_IP,
			'SignerUserAgent' => self::PREVIEW_SIGNER_USER_AGENT,
		];
	}

	private function getPreviewSignatureImage(string $renderMode): ?EmbeddedPdfImage {
		if (
			$renderMode !== SignerElementsService::RENDER_MODE_GRAPHIC_ONLY
			&& $renderMode !== SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION
		) {
			return null;
		}

		$assetPath = dirname(__DIR__, 3) . '/img/preview_signature.png';
		if (!is_readable($assetPath)) {
			return null;
		}
		return (new FilesystemPdfImageEmbedder())->embed($assetPath);
	}

	/**
	 * @param array<string, mixed> $resources
	 * @param array{width:int,height:int,data:string}|null $backgroundJpeg
	 */
	private function buildSinglePagePdf(float $width, float $height, string $contentStream, array $resources, ?array $backgroundJpeg, ?EmbeddedPdfImage $previewSignatureImage, string $renderMode): string {
		$stream = '';
		$images = [];

		if ($backgroundJpeg !== null) {
			$fit = $this->fitWithinBounds(
				width: $backgroundJpeg['width'],
				height: $backgroundJpeg['height'],
				maxWidth: (int)round($width),
				maxHeight: (int)round($height),
			);
			if ($fit['width'] > 0 && $fit['height'] > 0) {
				$stream .= sprintf(
					"q\n%d 0 0 %d %d %d cm\n/Im1 Do\nQ\n",
					$fit['width'],
					$fit['height'],
					$fit['x'],
					$fit['y'],
				);
				$images['Im1'] = new EmbeddedPdfImage([
					'Type' => '/XObject',
					'Subtype' => '/Image',
					'Width' => $backgroundJpeg['width'],
					'Height' => $backgroundJpeg['height'],
					'ColorSpace' => '/DeviceRGB',
					'BitsPerComponent' => 8,
					'Filter' => '/DCTDecode',
				], $backgroundJpeg['data']);
			}
		}

		if ($previewSignatureImage !== null) {
			$maxSignatureWidth = $renderMode === SignerElementsService::RENDER_MODE_GRAPHIC_ONLY
				? (int)round($width)
				: (int)round($width / 2.0);
			$fit = $this->fitWithinBounds(
				width: (int)$previewSignatureImage->dictionary['Width'],
				height: (int)$previewSignatureImage->dictionary['Height'],
				maxWidth: $maxSignatureWidth,
				maxHeight: (int)round($height),
				allowUpscale: false,
			);
			if ($fit['width'] > 0 && $fit['height'] > 0) {
				$stream .= sprintf(
					"q\n%d 0 0 %d %d %d cm\n/Im2 Do\nQ\n",
					$fit['width'],
					$fit['height'],
					$fit['x'],
					$fit['y'],
				);
				$images['Im2'] = $previewSignatureImage;
			}
		}

		// The exporter owns PDF object allocation, including Unicode fonts
		// and image masks. Only the in-memory preview images can be resolved.
		foreach (array_keys($images) as $alias) {
			$resources['XObject'][$alias] = ['Source' => $alias];
		}
		$imageEmbedder = new class($images) implements PdfImageEmbedderInterface {
			/** @param array<string, EmbeddedPdfImage> $images */
			public function __construct(
				private array $images,
			) {
			}

			#[\Override]
			public function embed(string $source): EmbeddedPdfImage {
				return $this->images[$source] ?? throw new \InvalidArgumentException('Unknown preview image.');
			}
		};
		$result = new CompileResult(
			contentStream: $stream . "q\n" . $contentStream . "\nQ\n",
			resources: $resources,
			bbox: [0.0, 0.0, $width, $height],
		);

		return (new SinglePagePdfExporter($imageEmbedder))->export($result);
	}

	/**
	 * @return array{width:int,height:int,data:string}|null
	 */
	private function resolveBackgroundJpeg(string $backgroundType): ?array {
		if ($backgroundType === 'deleted') {
			return null;
		}

		$blob = null;
		try {
			if ($backgroundType === 'default') {
				$blob = $this->signatureBackgroundService->getDefaultImageBlob();
			} elseif ($backgroundType === 'custom') {
				$blob = $this->signatureBackgroundService->getCustomImageBlob();
				if ($blob === null) {
					$blob = $this->signatureBackgroundService->getDefaultImageBlob();
				}
			} else {
				$blob = $this->signatureBackgroundService->getImage()->getContent();
			}
		} catch (NotFoundException|\Throwable) {
			return null;
		}

		if (!is_string($blob) || $blob === '') {
			return null;
		}

		return $this->convertImageBlobToJpeg($blob);
	}

	/**
	 * @return array{width:int,height:int,data:string}|null
	 */
	private function convertImageBlobToJpeg(string $blob): ?array {
		try {
			$image = new Imagick();
			$image->readImageBlob($blob);
			$image->setImageBackgroundColor('white');
			$image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
			$image->setImageFormat('jpeg');
			$image->setImageCompressionQuality(85);

			$width = max(1, (int)$image->getImageWidth());
			$height = max(1, (int)$image->getImageHeight());
			$data = $image->getImageBlob();
			$image->destroy();

			return [
				'width' => $width,
				'height' => $height,
				'data' => $data,
			];
		} catch (\Throwable) {
			return null;
		}
	}

	/**
	 * @return array{width:int,height:int,x:int,y:int}
	 */
	private function fitWithinBounds(int $width, int $height, int $maxWidth, int $maxHeight, bool $allowUpscale = true): array {
		if ($width <= 0 || $height <= 0 || $maxWidth <= 0 || $maxHeight <= 0) {
			return ['width' => 0, 'height' => 0, 'x' => 0, 'y' => 0];
		}

		$scale = min($maxWidth / $width, $maxHeight / $height);
		if (!$allowUpscale) {
			$scale = min(1.0, $scale);
		}
		$fitWidth = max(1, (int)floor($width * $scale));
		$fitHeight = max(1, (int)floor($height * $scale));

		return [
			'width' => $fitWidth,
			'height' => $fitHeight,
			'x' => (int)floor(max(0, $maxWidth - $fitWidth) / 2),
			'y' => (int)floor(max(0, $maxHeight - $fitHeight) / 2),
		];
	}

}
