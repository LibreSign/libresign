<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureStampPreview;

use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SignatureAppearanceXObjectDto;

class SignatureStampAppearanceBuilder {
	public function __construct(
		private SignatureTextService $signatureTextService,
	) {
	}

	public function buildXObject(
		int $width,
		int $height,
		string $renderMode,
		array $context = [],
		string $template = '',
		?float $templateFontSize = null,
		?float $signatureFontSize = null,
		?string $fallbackCommonName = null,
	): SignatureAppearanceXObjectDto {
		if ($renderMode === SignerElementsService::RENDER_MODE_GRAPHIC_ONLY) {
			return new SignatureAppearanceXObjectDto(stream: '', resources: []);
		}

		$context['ServerSignatureDate'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
			->format(\DateTimeInterface::ATOM);

		$textData = $this->signatureTextService->parse($template, $context);
		$parsed = trim((string)($textData['parsed'] ?? ''));

		$descFontSize = $templateFontSize
			?? (float)($textData['templateFontSize'] ?? $this->signatureTextService->getTemplateFontSize());
		$padding = max(2.0, $descFontSize * 0.15);
		$isDescriptionOnly = $renderMode === SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY;
		$columnWidth = $isDescriptionOnly ? (float)$width : $width / 2.0;
		$textWidth = $columnWidth - 2 * $padding;
		$blocks = [];

		if ($textWidth > 0 && $height > 0) {
			if ($renderMode === SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION) {
				$commonName = (string)($context['SignerCommonName'] ?? '');
				if ($commonName === '') {
					$commonName = $fallbackCommonName ?? '';
				}
				if ($commonName !== '') {
					$blocks[] = new \OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Dto\PlainTextBlock(
						$commonName,
						$padding,
						0.0,
						$textWidth,
						(float)$height,
						$signatureFontSize ?? $this->signatureTextService->getSignatureFontSize(),
						'center',
						'center',
					);
				}
			}
			if ($height > 2 && $parsed !== '') {
				$blocks[] = new \OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Dto\PlainTextBlock(
					$parsed,
					($isDescriptionOnly ? 0.0 : $columnWidth) + $padding,
					2.0,
					$textWidth,
					$height - 2.0,
					$descFontSize,
				);
			}
		}

		if ($blocks === []) {
			return new SignatureAppearanceXObjectDto(stream: '', resources: []);
		}

		$compiler = new \OCA\Libresign\Vendor\LibreSign\XObjectTemplate\PlainTextCompiler(
			(new \OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Pdf\TemplateDocumentBuilder())->withUnicodeFontFiles([
				dirname(__DIR__, 3) . '/3rdparty/composer/mpdf/mpdf/ttfonts/DejaVuSansCondensed.ttf',
			]),
		);
		$result = $compiler->compile($blocks, (float)$width, (float)$height);

		return new SignatureAppearanceXObjectDto(
			stream: $result->contentStream,
			resources: $result->resources,
			resourcesBuilder: (new \OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Pdf\SignerPhpCoreXObjectResourceBuilder())->resourcesBuilder(),
		);
	}
}
