<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Font;

class BundledFontLocator {
	private const array FONT_DIRECTORY_CANDIDATES = [
		'3rdparty/composer/mpdf/mpdf/ttfonts',
		'3rdparty/vendor/mpdf/mpdf/ttfonts',
	];

	/**
	 * @return list<string>
	 */
	public function getFontDirectories(): array {
		return array_values(array_filter(
			$this->getCandidateDirectories(),
			static fn (string $path): bool => is_dir($path),
		));
	}

	public function findFontFile(string $fontFile): ?string {
		foreach ($this->getFontDirectories() as $directory) {
			$candidate = $directory . '/' . $fontFile;
			if (is_file($candidate)) {
				return $candidate;
			}
		}

		return null;
	}

	public function requireFontFile(string $fontFile): string {
		$fontPath = $this->findFontFile($fontFile);
		if ($fontPath !== null) {
			return $fontPath;
		}

		throw new \RuntimeException(sprintf('Bundled font not found: %s', $fontFile));
	}

	/**
	 * @return list<string>
	 */
	private function getCandidateDirectories(): array {
		$appRoot = dirname(__DIR__, 3);

		return array_map(
			static fn (string $relativePath): string => $appRoot . '/' . $relativePath,
			self::FONT_DIRECTORY_CANDIDATES,
		);
	}
}
