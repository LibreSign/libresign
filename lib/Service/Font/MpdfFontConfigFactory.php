<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Font;

use OCA\Libresign\Vendor\Mpdf\Config\ConfigVariables;
use OCA\Libresign\Vendor\Mpdf\Config\FontVariables;

class MpdfFontConfigFactory {
	public function __construct(
		private FontConfigService $fontConfigService,
	) {
	}

	/**
	 * @return array{fontDir: list<string>, fontdata: array<string, array<string, mixed>>, default_font: string}
	 */
	public function getConfig(): array {
		$fontDirectories = $this->getDefaultFontDirectories();
		$fontData = $this->getDefaultFontData();
		$defaultFont = $this->fontConfigService->getActiveFontFamily();

		$configuredFont = $this->fontConfigService->getConfiguredTemplateFont();
		if ($configuredFont !== null) {
			array_unshift($fontDirectories, $configuredFont->getDirectory());
			$fontDirectories = array_values(array_unique($fontDirectories));
			$fontData[$configuredFont->getFamily()] = [
				'R' => $configuredFont->getRegular(),
				'B' => $configuredFont->getBold(),
				'I' => $configuredFont->getItalic(),
				'BI' => $configuredFont->getBoldItalic(),
			];
		}

		return [
			'fontDir' => $fontDirectories,
			'fontdata' => $fontData,
			'default_font' => $defaultFont,
		];
	}

	public function getFontFamily(): string {
		return $this->fontConfigService->getActiveFontFamily();
	}

	/**
	 * @return list<string>
	 */
	private function getDefaultFontDirectories(): array {
		$defaults = (new ConfigVariables())->getDefaults();
		$fontDirectories = $defaults['fontDir'] ?? [];
		if (!is_array($fontDirectories)) {
			$fontDirectories = [$fontDirectories];
		}

		$bundledMpdfFontsDirectory = __DIR__ . '/../../../3rdparty/composer/mpdf/mpdf/ttfonts';
		if (is_dir($bundledMpdfFontsDirectory)) {
			$fontDirectories[] = $bundledMpdfFontsDirectory;
		}

		return array_values(array_unique(array_filter(
			$fontDirectories,
			static fn ($path): bool => is_string($path) && $path !== '' && is_dir($path),
		)));
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function getDefaultFontData(): array {
		$defaults = (new FontVariables())->getDefaults();
		$fontData = $defaults['fontdata'] ?? [];

		return is_array($fontData) ? $fontData : [];
	}
}
