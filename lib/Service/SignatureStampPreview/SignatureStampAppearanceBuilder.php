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
		$availableWidth = $isDescriptionOnly ? (float)$width : (float)$width / 2.0;

		$stream = '';

		if ($renderMode === SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION) {
			$commonName = !empty($context['SignerCommonName'])
				? (string)$context['SignerCommonName']
				: ($fallbackCommonName ?? '');

			if ($commonName !== '') {
				$nameFontSize = $signatureFontSize ?? $this->signatureTextService->getSignatureFontSize();
				$leftHalfW = (float)$width / 2.0;
				$nameLines = $this->wrapTextForPdf($commonName, $leftHalfW - $leftPadding * 2, $nameFontSize);
				$nameLineCount = count($nameLines);
				$totalNameHeight = $nameLineCount * $nameFontSize * 1.0;
				$nameStartY = ((float)$height + $totalNameHeight) / 2.0 - $nameFontSize;
				$nameStartY = max(0.0, $nameStartY);
				$nameY = $nameStartY;
				$estimatedCharWidth = $nameFontSize * 0.52;
				foreach ($nameLines as $nameLine) {
					$lineWidth = strlen($nameLine) * $estimatedCharWidth;
					$nameX = max($leftPadding, ($leftHalfW - $lineWidth) / 2.0);
					$escaped = $this->escapePdfText($nameLine);
					$stream .= "BT\n";
					$stream .= sprintf("/F1 %.2F Tf\n", $nameFontSize);
					$stream .= "0 0 0 rg\n";
					$stream .= sprintf("%.2F %.2F Td\n", $nameX, $nameY);
					$stream .= sprintf("(%s) Tj\n", $escaped);
					$stream .= "ET\n";
					$nameY -= $nameFontSize * 1.0;
				}
			}
		}

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
	public function wrapTextForPdf(string $line, float $availableWidth, float $fontSize): array {
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

	public function escapePdfText(string $value): string {
		$value = str_replace('\\', '\\\\', $value);
		$value = str_replace('(', '\\(', $value);
		$value = str_replace(')', '\\)', $value);

		return $value;
	}
}
