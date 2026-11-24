<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignatureStampPreview;

use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use SignerPHP\Application\DTO\SignatureAppearanceXObjectDto;

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
		$descLineHeight = $descFontSize * 1.0;
		$leftPadding = max(2.0, $descFontSize * 0.15);

		$isDescriptionOnly = $renderMode === SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY;
		$textStartX = $isDescriptionOnly ? $leftPadding : ((float)$width / 2.0) + $leftPadding;
		$availableWidth = ($isDescriptionOnly ? (float)$width : (float)$width / 2.0) - $leftPadding * 2;

		$layout = new SignatureStampPdfLayout();

		if ($renderMode === SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION) {
			$commonName = !empty($context['SignerCommonName'])
				? (string)$context['SignerCommonName']
				: ($fallbackCommonName ?? '');

			if ($commonName !== '') {
				$nameFontSize = $signatureFontSize ?? $this->signatureTextService->getSignatureFontSize();
				$leftHalfW = (float)$width / 2.0;
				$nameLines = $layout->wrapText($commonName, $leftHalfW - $leftPadding * 2, $nameFontSize);
				$nameLineCount = count($nameLines);
				$totalNameHeight = $nameLineCount * $nameFontSize * 1.0;
				$nameStartY = ((float)$height + $totalNameHeight) / 2.0 - $nameFontSize;
				$nameStartY = max(0.0, $nameStartY);
				$nameY = $nameStartY;
				foreach ($nameLines as $nameLine) {
					$lineWidth = $layout->measureText($nameLine, $nameFontSize);
					$nameX = max($leftPadding, ($leftHalfW - $lineWidth) / 2.0);
					$layout->addLine($nameLine, $nameX, $nameY, $nameFontSize);
					$nameY -= $nameFontSize * 1.0;
				}
			}
		}

		$currentY = (float)$height - $descFontSize - 2.0;
		foreach (preg_split('/\r\n|\r|\n/', $parsed) ?: [] as $line) {
			$wrappedLines = $layout->wrapText($line, $availableWidth, $descFontSize);
			foreach ($wrappedLines as $wrappedLine) {
				if ($currentY < 0) {
					break 2;
				}
				$layout->addLine($wrappedLine, $textStartX, $currentY, $descFontSize);
				$currentY -= $descLineHeight;
			}
		}

		return $layout->build($width, $height);
	}

	/**
	 * @return string[]
	 */
	public function wrapTextForPdf(string $line, float $availableWidth, float $fontSize): array {
		return (new SignatureStampPdfLayout())->wrapText($line, $availableWidth, $fontSize);
	}

	public function escapePdfText(string $value): string {
		return SignatureStampPdfLayout::escapePdfText($value);
	}
}
