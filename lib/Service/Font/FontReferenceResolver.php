<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Font;

use OCA\Libresign\Exception\LibresignException;
use Psr\Log\LoggerInterface;

class FontReferenceResolver {
	private const string DEFAULT_BUNDLED_FALLBACK_FONT_FILE = 'DejaVuSerifCondensed.ttf';

	public function __construct(
		private BundledFontLocator $bundledFontLocator,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param list<string> $availableSystemFonts
	 * @throws LibresignException
	 */
	public function resolveFontReference(
		array $availableSystemFonts,
		string $fallbackFontFile = self::DEFAULT_BUNDLED_FALLBACK_FONT_FILE,
	): string {
		$availableSystemFonts = array_values(array_filter(
			$availableSystemFonts,
			static fn (mixed $font): bool => is_string($font) && $font !== '',
		));

		if ($availableSystemFonts !== []) {
			return $availableSystemFonts[0];
		}

		$fallbackFont = $this->bundledFontLocator->findFontFile($fallbackFontFile);
		if ($fallbackFont !== null) {
			return $fallbackFont;
		}

		$message = 'No fonts available at system, and bundled fallback font not found: ' . $fallbackFontFile;
		$this->logger->error($message, [
			'fontFile' => $fallbackFontFile,
		]);

		throw new LibresignException($message);
	}

	/**
	 * @param list<string> $availableSystemFonts
	 * @throws LibresignException
	 */
	public function resolve(
		array $availableSystemFonts,
		string $fallbackFontFile = self::DEFAULT_BUNDLED_FALLBACK_FONT_FILE,
	): string {
		return $this->resolveFontReference($availableSystemFonts, $fallbackFontFile);
	}
}
