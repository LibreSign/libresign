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
	public const string DEFAULT_FONT_FAMILY = 'dejavusanscondensed';

	private BundledFontLocator $bundledFontLocator;
	private SystemFontCatalog $systemFontCatalog;

	public function __construct(
		?BundledFontLocator $bundledFontLocator = null,
		?SystemFontCatalog $systemFontCatalog = null,
	) {
		$this->bundledFontLocator = $bundledFontLocator ?? new BundledFontLocator();
		$this->systemFontCatalog = $systemFontCatalog ?? new SystemFontCatalog();
	}

	/**
	 * @return array{fontDir: list<string>, fontdata: array<string, array<string, mixed>>, default_font: string}
	 */
	public function getConfig(): array {
		$fontDirectories = array_values(array_unique(array_merge(
			$this->getDefaultFontDirectories(),
			$this->systemFontCatalog->getFontDirectories(),
		)));
		$fontData = $this->getDefaultFontData();

		foreach ($this->systemFontCatalog->getFontData() as $family => $variants) {
			$fontData[$family] ??= $variants;
		}

		return [
			'fontDir' => $fontDirectories,
			'fontdata' => $fontData,
			'default_font' => self::DEFAULT_FONT_FAMILY,
		];
	}

	public function getFontFamily(): string {
		return self::DEFAULT_FONT_FAMILY;
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

		$fontDirectories = array_merge($fontDirectories, $this->bundledFontLocator->getFontDirectories());

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
