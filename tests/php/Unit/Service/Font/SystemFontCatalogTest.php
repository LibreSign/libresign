<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service\Font;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Service\Font\SystemFontCatalog;
use PHPUnit\Framework\Attributes\DataProvider;

final class SystemFontCatalogTest extends \OCA\Libresign\Tests\Unit\TestCase {
	/**
	 * @var list<string>
	 */
	private array $temporaryDirectories = [];

	#[\Override]
	public function setUp(): void {
	}

	#[\Override]
	public function tearDown(): void {
		foreach ($this->temporaryDirectories as $directory) {
			$this->removeDirectory($directory);
		}

		parent::tearDown();
	}

	/**
	 * @param list<string> $fontFiles
	 */
	private function createFontDirectory(array $fontFiles): string {
		$fontDirectory = sys_get_temp_dir() . '/libresign-system-font-catalog-' . bin2hex(random_bytes(8));
		$this->assertTrue(mkdir($fontDirectory, 0777, true) || is_dir($fontDirectory));
		$this->temporaryDirectories[] = $fontDirectory;

		foreach ($fontFiles as $relativePath) {
			$destinationPath = $fontDirectory . '/' . $relativePath;
			if (!is_dir(dirname($destinationPath))) {
				$this->assertTrue(mkdir(dirname($destinationPath), 0777, true) || is_dir(dirname($destinationPath)));
			}
			$this->assertNotFalse(file_put_contents($destinationPath, 'font'));
		}

		return $fontDirectory;
	}

	private function removeDirectory(string $directory): void {
		if (!is_dir($directory)) {
			return;
		}

		$items = scandir($directory);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if (in_array($item, ['.', '..'], true)) {
				continue;
			}

			$path = $directory . '/' . $item;
			if (is_dir($path)) {
				$this->removeDirectory($path);
			} else {
				unlink($path);
			}
		}

		rmdir($directory);
	}

	public function testReturnsEmptyCatalogWhenCandidateDirectoriesDoNotExist(): void {
		$catalog = new SystemFontCatalog(sys_get_temp_dir() . '/libresign-system-font-catalog-missing-' . bin2hex(random_bytes(4)));

		$this->assertSame([], $catalog->getFontDirectories());
		$this->assertSame([], $catalog->getFontData());
	}

	/**
	 * @param list<string> $fontFiles
	 * @param array<string, array<string, string>> $expectedFontData
	 */
	#[DataProvider('provideFontDataScenarios')]
	public function testDiscoversExpectedFontData(array $fontFiles, array $expectedFontData): void {
		$fontDirectory = $this->createFontDirectory($fontFiles);
		$catalog = new SystemFontCatalog($fontDirectory);

		$this->assertSame($expectedFontData, $catalog->getFontData());
		$this->assertNotEmpty($catalog->getFontDirectories());
	}

	public function testIgnoresFontFilesWithoutUsableFamilyName(): void {
		$fontDirectory = $this->createFontDirectory([
			'invalid/---.ttf',
		]);
		$catalog = new SystemFontCatalog($fontDirectory);

		$this->assertSame([], $catalog->getFontData());
		$this->assertSame([], $catalog->getFontDirectories());
	}

	public function testUsesHomeFontDirectoriesWhenNoCandidatesAreProvided(): void {
		$homeDirectory = sys_get_temp_dir() . '/libresign-system-font-home-' . bin2hex(random_bytes(8));
		$localFontsDirectory = $homeDirectory . '/.local/share/fonts';
		$legacyFontsDirectory = $homeDirectory . '/.fonts';
		$this->assertTrue(mkdir($localFontsDirectory, 0777, true) || is_dir($localFontsDirectory));
		$this->assertTrue(mkdir($legacyFontsDirectory, 0777, true) || is_dir($legacyFontsDirectory));
		$this->temporaryDirectories[] = $homeDirectory;
		$this->assertNotFalse(file_put_contents($localFontsDirectory . '/HomeSans-Regular.ttf', 'font'));
		$this->assertNotFalse(file_put_contents($legacyFontsDirectory . '/LegacySans-Bold.ttf', 'font'));

		$previousHome = getenv('HOME');
		putenv('HOME=' . $homeDirectory);

		try {
			$catalog = new SystemFontCatalog();
			$fontDirectories = $catalog->getFontDirectories();
			$fontData = $catalog->getFontData();

			$this->assertContains(realpath($localFontsDirectory), $fontDirectories);
			$this->assertContains(realpath($legacyFontsDirectory), $fontDirectories);
			$this->assertArrayHasKey('homesans', $fontData);
			$this->assertArrayHasKey('legacysans', $fontData);
		} finally {
			putenv($previousHome === false ? 'HOME' : 'HOME=' . $previousHome);
		}
	}

	public static function provideFontDataScenarios(): array {
		return [
			'explicit variants are mapped directly' => [
				[
					'custom/CustomSans-Regular.ttf',
					'custom/CustomSans-Bold.ttf',
					'custom/CustomSans-Italic.ttf',
					'custom/CustomSans-BoldItalic.ttf',
					'custom/Ignore-Me.woff',
				],
				[
					'customsans' => [
						'R' => 'CustomSans-Regular.ttf',
						'B' => 'CustomSans-Bold.ttf',
						'I' => 'CustomSans-Italic.ttf',
						'BI' => 'CustomSans-BoldItalic.ttf',
					],
				],
			],
			'regular-only fonts are reused for all variants' => [
				[
					'fallback/CustomSans-Regular.otf',
				],
				[
					'customsans' => [
						'R' => 'CustomSans-Regular.otf',
						'B' => 'CustomSans-Regular.otf',
						'I' => 'CustomSans-Regular.otf',
						'BI' => 'CustomSans-Regular.otf',
					],
				],
			],
			'missing regular falls back to bold' => [
				[
					'fallback/FancyFont-Bold.ttf',
				],
				[
					'fancyfont' => [
						'R' => 'FancyFont-Bold.ttf',
						'B' => 'FancyFont-Bold.ttf',
						'I' => 'FancyFont-Bold.ttf',
						'BI' => 'FancyFont-Bold.ttf',
					],
				],
			],
			'missing regular falls back to italic' => [
				[
					'fallback/FancyFont-Italic.ttf',
				],
				[
					'fancyfont' => [
						'R' => 'FancyFont-Italic.ttf',
						'B' => 'FancyFont-Italic.ttf',
						'I' => 'FancyFont-Italic.ttf',
						'BI' => 'FancyFont-Italic.ttf',
					],
				],
			],
			'missing regular falls back to bold italic' => [
				[
					'fallback/FancyFont-BoldItalic.ttf',
				],
				[
					'fancyfont' => [
						'R' => 'FancyFont-BoldItalic.ttf',
						'B' => 'FancyFont-BoldItalic.ttf',
						'I' => 'FancyFont-BoldItalic.ttf',
						'BI' => 'FancyFont-BoldItalic.ttf',
					],
				],
			],
			'camel case and variant suffixes are normalized' => [
				[
					'normalized/FooterSerifRoman.ttf',
					'normalized/FooterSerifSemiBold.ttf',
					'normalized/FooterSerifOblique.ttf',
					'normalized/FooterSerifSemiBoldItalic.ttf',
					'normalized/README.md',
				],
				[
					'footerserif' => [
						'R' => 'FooterSerifRoman.ttf',
						'B' => 'FooterSerifSemiBold.ttf',
						'I' => 'FooterSerifOblique.ttf',
						'BI' => 'FooterSerifSemiBoldItalic.ttf',
					],
				],
			],
		];
	}

	public function testGetFontDirectoriesReturnsSortedUniqueDirectories(): void {
		$fontDirectory = $this->createFontDirectory([
			'z-last/ZFont-Regular.ttf',
			'a-first/AFont-Regular.ttf',
			'a-first/nested/NestedFont-Regular.ttf',
		]);
		$catalog = new SystemFontCatalog($fontDirectory, $fontDirectory . '/', $fontDirectory);

		$this->assertSame([
			realpath($fontDirectory . '/a-first'),
			realpath($fontDirectory . '/a-first/nested'),
			realpath($fontDirectory . '/z-last'),
		], $catalog->getFontDirectories());
	}
}
