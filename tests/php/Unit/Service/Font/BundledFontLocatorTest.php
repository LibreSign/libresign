<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service\Font;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Service\Font\BundledFontLocator;
use PHPUnit\Framework\Attributes\DataProvider;

final class BundledFontLocatorTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private BundledFontLocator $locator;

	#[\Override]
	public function setUp(): void {
		$this->locator = new BundledFontLocator();
	}

	public function testReturnsExistingBundledFontDirectories(): void {
		$directories = $this->locator->getFontDirectories();

		$this->assertNotEmpty($directories);
		foreach ($directories as $directory) {
			$this->assertDirectoryExists($directory);
		}
	}

	#[DataProvider('provideBundledFontFiles')]
	public function testFindAndRequireBundledFontFile(string $fontFile): void {
		$fontPath = $this->locator->findFontFile($fontFile);

		$this->assertNotNull($fontPath);
		$this->assertFileExists($fontPath);
		$this->assertSame($fontPath, $this->locator->requireFontFile($fontFile));
	}

	public static function provideBundledFontFiles(): array {
		return [
			'DejaVu Sans bundled font' => ['DejaVuSansCondensed.ttf'],
			'DejaVu Serif bundled font' => ['DejaVuSerifCondensed.ttf'],
		];
	}

	public function testMissingBundledFontReturnsNullAndThrows(): void {
		$this->assertNull($this->locator->findFontFile('Missing-Bundled-Font.ttf'));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Bundled font not found: Missing-Bundled-Font.ttf');

		$this->locator->requireFontFile('Missing-Bundled-Font.ttf');
	}
}
