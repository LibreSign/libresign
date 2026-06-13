<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Font;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class SystemFontCatalog {
	private const array DEFAULT_FONT_DIRECTORY_CANDIDATES = [
		'/usr/local/share/fonts',
		'/usr/share/fonts',
		'/usr/X11R6/lib/X11/fonts',
		'/System/Library/Fonts',
		'/Library/Fonts',
	];
	private const array SUPPORTED_EXTENSIONS = ['ttf', 'otf'];
	private const array VARIANT_SUFFIX_PATTERNS = [
		'BI' => '/\b(?:extra|ultra|semi|demi)?\s*(?:bold|black|heavy)\s+(?:italic|oblique)\b$/i',
		'I' => '/\b(?:italic|oblique)\b$/i',
		'B' => '/\b(?:extra|ultra|semi|demi)?\s*(?:bold|black|heavy)\b$/i',
		'R' => '/\b(?:regular|roman|book|medium|normal)\b$/i',
	];

	/**
	 * @var list<string>|null
	 */
	private ?array $candidateDirectories;
	/**
	 * @var list<string>|null
	 */
	private ?array $fontDirectories = null;
	/**
	 * @var array<string, array{R: string, B: string, I: string, BI: string}>|null
	 */
	private ?array $fontData = null;

	public function __construct(string ...$candidateDirectories) {
		$this->candidateDirectories = $candidateDirectories === [] ? null : $candidateDirectories;
	}

	/**
	 * @return list<string>
	 */
	public function getFontDirectories(): array {
		$this->discoverFonts();

		return $this->fontDirectories ?? [];
	}

	/**
	 * @return array<string, array{R: string, B: string, I: string, BI: string}>
	 */
	public function getFontData(): array {
		$this->discoverFonts();

		return $this->fontData ?? [];
	}

	private function discoverFonts(): void {
		if ($this->fontDirectories !== null && $this->fontData !== null) {
			return;
		}

		$fontDirectories = [];
		$discoveredVariants = [];

		foreach ($this->getCandidateDirectories() as $candidateDirectory) {
			$realCandidateDirectory = realpath($candidateDirectory);
			if ($realCandidateDirectory === false || !is_dir($realCandidateDirectory)) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($realCandidateDirectory, FilesystemIterator::SKIP_DOTS),
				RecursiveIteratorIterator::LEAVES_ONLY,
				RecursiveIteratorIterator::CATCH_GET_CHILD,
			);

			foreach ($iterator as $fileInfo) {
				if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
					continue;
				}

				$fontDescriptor = $this->createFontDescriptor($fileInfo);
				if ($fontDescriptor === null) {
					continue;
				}

				$directory = realpath($fileInfo->getPath());
				if ($directory === false) {
					continue;
				}

				$fontDirectories[$directory] = $directory;
				$discoveredVariants[$fontDescriptor['family']][$fontDescriptor['variant']] ??= $fileInfo->getBasename();
			}
		}

		$fontData = [];
		foreach ($discoveredVariants as $family => $variants) {
			$regular = $variants['R'] ?? $variants['B'] ?? $variants['I'] ?? $variants['BI'] ?? null;
			if ($regular === null) {
				continue;
			}

			$fontData[$family] = [
				'R' => $regular,
				'B' => $variants['B'] ?? $variants['BI'] ?? $regular,
				'I' => $variants['I'] ?? $variants['BI'] ?? $regular,
				'BI' => $variants['BI'] ?? $variants['B'] ?? $variants['I'] ?? $regular,
			];
		}

		ksort($fontData);
		$fontDirectories = array_values($fontDirectories);
		sort($fontDirectories);

		$this->fontDirectories = $fontDirectories;
		$this->fontData = $fontData;
	}

	/**
	 * @return list<string>
	 */
	private function getCandidateDirectories(): array {
		$directories = $this->candidateDirectories ?? $this->getDefaultCandidateDirectories();

		return array_values(array_unique(array_filter(
			array_map(
				static fn (string $directory): string => rtrim($directory, DIRECTORY_SEPARATOR),
				array_filter($directories, static fn (mixed $directory): bool => is_string($directory) && $directory !== ''),
			),
			static fn (string $directory): bool => $directory !== '',
		)));
	}

	/**
	 * @return list<string>
	 */
	private function getDefaultCandidateDirectories(): array {
		$directories = self::DEFAULT_FONT_DIRECTORY_CANDIDATES;
		$homeDirectory = getenv('HOME');

		if (is_string($homeDirectory) && $homeDirectory !== '') {
			array_unshift(
				$directories,
				$homeDirectory . '/.local/share/fonts',
				$homeDirectory . '/.fonts',
			);
		}

		return $directories;
	}

	/**
	 * @return array{family: string, variant: 'R'|'B'|'I'|'BI'}|null
	 */
	private function createFontDescriptor(SplFileInfo $fileInfo): ?array {
		$extension = strtolower($fileInfo->getExtension());
		if (!in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
			return null;
		}

		return $this->resolveFamilyAndVariant((string)pathinfo($fileInfo->getBasename(), PATHINFO_FILENAME));
	}

	/**
	 * @return array{family: string, variant: 'R'|'B'|'I'|'BI'}|null
	 */
	private function resolveFamilyAndVariant(string $fontName): ?array {
		$normalizedFontName = $this->normalizeFontName($fontName);
		if ($normalizedFontName === '') {
			return null;
		}

		$variant = 'R';
		$familyName = $normalizedFontName;
		foreach (self::VARIANT_SUFFIX_PATTERNS as $candidateVariant => $pattern) {
			if (preg_match($pattern, $normalizedFontName) !== 1) {
				continue;
			}

			$variant = $candidateVariant;
			$familyName = trim((string)preg_replace($pattern, '', $normalizedFontName));
			break;
		}

		$family = strtolower((string)preg_replace('/[^a-z0-9]+/i', '', $familyName));
		if ($family === '') {
			return null;
		}

		return [
			'family' => $family,
			'variant' => $variant,
		];
	}

	private function normalizeFontName(string $fontName): string {
		$normalized = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $fontName);
		$normalized = preg_replace('/(?<=[A-Z])(?=[A-Z][a-z])/', ' ', is_string($normalized) ? $normalized : $fontName);
		$normalized = str_replace(['_', '-'], ' ', is_string($normalized) ? $normalized : $fontName);
		$normalized = preg_replace('/\s+/', ' ', $normalized);

		return trim(is_string($normalized) ? $normalized : $fontName);
	}
}
