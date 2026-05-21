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

		$contentStream = $xObject->stream;

		$backgroundJpeg = $this->resolveBackgroundJpeg($backgroundType);
		$previewSignatureImage = $this->getPreviewSignatureImage($renderMode);

		return $this->buildSinglePagePdf($width, $height, $contentStream, $backgroundJpeg, $previewSignatureImage, $renderMode);
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

	/**
	 * Loads the preview signature asset (PNG converted to JPEG) for placement in the preview PDF.
	 *
	 * The asset is located at img/preview_signature.png and is embedded directly into the
	 * preview PDF for graphic modes. This simplifies the implementation significantly compared
	 * to procedurally drawing Bezier curves, and makes the preview nature of the graphic clear.
	 *
	 * @return array{kind:'rgba',width:int,height:int,rgbData:string,alphaData:string}|null
	 */
	private function getPreviewSignatureImage(string $renderMode): ?array {
		if (
			$renderMode !== SignerElementsService::RENDER_MODE_GRAPHIC_ONLY
			&& $renderMode !== SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION
		) {
			return null;
		}

		$assetPath = dirname(__DIR__, 3) . '/img/preview_signature.png';
		if (!file_exists($assetPath)) {
			return null;
		}
		$blob = @file_get_contents($assetPath);
		if (!is_string($blob) || $blob === '') {
			return null;
		}

		return $this->convertImageBlobToPdfRgbaImage($blob);
	}

	/**
	 * @param array{width:int,height:int,data:string}|null $backgroundJpeg
	 * @param array{kind:'rgba',width:int,height:int,rgbData:string,alphaData:string}|null $previewSignatureImage
	 */
	private function buildSinglePagePdf(float $width, float $height, string $contentStream, ?array $backgroundJpeg, ?array $previewSignatureImage, string $renderMode): string {
		$widthFormatted = number_format($width, 2, '.', '');
		$heightFormatted = number_format($height, 2, '.', '');
		$stream = '';
		$xObjectReferences = [];
		$nextObjectId = 5;

		$objects = [
			1 => '<< /Type /Catalog /Pages 2 0 R >>',
			2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
		];

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
				$backgroundObjectId = $nextObjectId;
				$nextObjectId += 1;
				$xObjectReferences[] = '/Im1 ' . $backgroundObjectId . ' 0 R';
				$objects[$backgroundObjectId] = sprintf(
					"<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%sendstream",
					$backgroundJpeg['width'],
					$backgroundJpeg['height'],
					strlen($backgroundJpeg['data']),
					$backgroundJpeg['data'],
				);
			}
		}

		if ($previewSignatureImage !== null) {
			$maxSignatureWidth = $renderMode === SignerElementsService::RENDER_MODE_GRAPHIC_ONLY
				? (int)round($width)
				: (int)round($width / 2.0);
			$fit = $this->fitWithinBounds(
				width: $previewSignatureImage['width'],
				height: $previewSignatureImage['height'],
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
				$signatureObjectId = $nextObjectId;
				$nextObjectId += 1;

				$alphaMaskObjectId = $nextObjectId;
				$nextObjectId += 1;

				$xObjectReferences[] = '/Im2 ' . $signatureObjectId . ' 0 R';

				$alphaData = gzcompress($previewSignatureImage['alphaData']) ?: '';
				$objects[$alphaMaskObjectId] = sprintf(
					"<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length %d >>\nstream\n%sendstream",
					$previewSignatureImage['width'],
					$previewSignatureImage['height'],
					strlen($alphaData),
					$alphaData,
				);

				$rgbData = gzcompress($previewSignatureImage['rgbData']) ?: '';
				$objects[$signatureObjectId] = sprintf(
					"<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask %d 0 R /Filter /FlateDecode /Length %d >>\nstream\n%sendstream",
					$previewSignatureImage['width'],
					$previewSignatureImage['height'],
					$alphaMaskObjectId,
					strlen($rgbData),
					$rgbData,
				);
			}
		}

		$stream .= "q\n" . $contentStream . "Q\n";
		$contentObjectId = $nextObjectId;

		$xObjectDict = $xObjectReferences !== [] ? ' /XObject << ' . implode(' ', $xObjectReferences) . ' >>' : '';
		$objects[3] = sprintf(
			'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources << /Font << /F1 4 0 R >>%s >> /Contents %d 0 R >>',
			$widthFormatted,
			$heightFormatted,
			$xObjectDict,
			$contentObjectId,
		);

		$objects[$contentObjectId] = sprintf(
			"<< /Length %d >>\nstream\n%sendstream",
			strlen($stream),
			$stream,
		);

		return $this->assemblePdf($objects);
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

	/**
	 * @return array{kind:'rgba',width:int,height:int,rgbData:string,alphaData:string}|null
	 */
	private function convertImageBlobToPdfRgbaImage(string $blob): ?array {
		try {
			$image = new Imagick();
			$image->readImageBlob($blob);

			$width = max(1, (int)$image->getImageWidth());
			$height = max(1, (int)$image->getImageHeight());

			$rgbPixels = $image->exportImagePixels(0, 0, $width, $height, 'RGB', Imagick::PIXEL_CHAR);
			$alphaPixels = $image->exportImagePixels(0, 0, $width, $height, 'A', Imagick::PIXEL_CHAR);

			if (!is_array($rgbPixels) || !is_array($alphaPixels)) {
				$image->destroy();
				return null;
			}

			$rgbData = $this->pixelsToBinary($rgbPixels);
			$alphaData = $this->pixelsToBinary($alphaPixels);

			$image->destroy();

			if ($rgbData === '' || $alphaData === '') {
				return null;
			}

			return [
				'kind' => 'rgba',
				'width' => $width,
				'height' => $height,
				'rgbData' => $rgbData,
				'alphaData' => $alphaData,
			];
		} catch (\Throwable) {
			return null;
		}
	}

	/**
	 * @param list<int|float|string> $pixels
	 */
	private function pixelsToBinary(array $pixels): string {
		$data = '';
		$chunk = [];
		$chunkSize = 8192;

		foreach ($pixels as $value) {
			$chunk[] = (int)$value;
			if (count($chunk) >= $chunkSize) {
				$data .= pack('C*', ...$chunk);
				$chunk = [];
			}
		}

		if ($chunk !== []) {
			$data .= pack('C*', ...$chunk);
		}

		return $data;
	}

	/**
	 * @param array<int, string> $objects
	 */
	private function assemblePdf(array $objects): string {
		$pdf = "%PDF-1.4\n";
		$offsets = [0 => 0];

		$objectCount = count($objects);
		for ($i = 1; $i <= $objectCount; $i++) {
			$offsets[$i] = strlen($pdf);
			$pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $i, $objects[$i]);
		}

		$xrefOffset = strlen($pdf);
		$pdf .= sprintf("xref\n0 %d\n", $objectCount + 1);
		$pdf .= "0000000000 65535 f \n";
		for ($i = 1; $i <= $objectCount; $i++) {
			$pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
		}

		$pdf .= sprintf(
			"trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF",
			$objectCount + 1,
			$xrefOffset,
		);

		return $pdf;
	}
}
