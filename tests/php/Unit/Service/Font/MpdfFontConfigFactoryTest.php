<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service\Font;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Service\Font\BundledFontLocator;
use OCA\Libresign\Service\Font\MpdfFontConfigFactory;
use OCA\Libresign\Service\Font\SystemFontCatalog;
use OCA\Libresign\Vendor\Mpdf\Config\FontVariables;
use PHPUnit\Framework\Attributes\DataProvider;

final class MpdfFontConfigFactoryTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private BundledFontLocator $bundledFontLocator;

	/**
	 * @var list<string>
	 */
	private array $temporaryDirectories = [];

	#[\Override]
	public function setUp(): void {
		$this->bundledFontLocator = new BundledFontLocator();
	}

	#[\Override]
	public function tearDown(): void {
		foreach ($this->temporaryDirectories as $directory) {
			$this->removeDirectory($directory);
		}

		parent::tearDown();
	}

	private function getClass(?SystemFontCatalog $systemFontCatalog = null): MpdfFontConfigFactory {
		return new MpdfFontConfigFactory(
			$this->bundledFontLocator,
			$systemFontCatalog ?? new SystemFontCatalog(sys_get_temp_dir() . '/libresign-font-catalog-missing-' . bin2hex(random_bytes(4))),
		);
	}

	/**
	 * @param list<string> $fontFiles
	 */
	private function createDiscoveredFontDirectory(array $fontFiles): string {
		$fontDirectory = sys_get_temp_dir() . '/libresign-mpdf-font-factory-' . bin2hex(random_bytes(8));
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

	public function testDefaultConfigUsesMpdfDefaults(): void {
		$config = $this->getClass()->getConfig();
		$defaultFontData = (new FontVariables())->getDefaults()['fontdata'];

		$this->assertSame(MpdfFontConfigFactory::DEFAULT_FONT_FAMILY, $config['default_font']);
		$this->assertNotEmpty($config['fontDir']);
		foreach ($config['fontDir'] as $fontDirectory) {
			$this->assertDirectoryExists($fontDirectory);
		}
		$this->assertArrayHasKey(MpdfFontConfigFactory::DEFAULT_FONT_FAMILY, $config['fontdata']);
		$this->assertSame(
			$defaultFontData[MpdfFontConfigFactory::DEFAULT_FONT_FAMILY],
			$config['fontdata'][MpdfFontConfigFactory::DEFAULT_FONT_FAMILY],
		);
	}

	/**
	 * @param list<string> $fontFiles
	 * @param array<string, string> $expectedFontData
	 */
	#[DataProvider('provideDiscoveredFontScenarios')]
	public function testDiscoveredFontScenariosAffectMpdfConfig(
		array $fontFiles,
		string $expectedRegisteredFamily,
		array $expectedFontData,
	): void {
		$fontDirectory = $this->createDiscoveredFontDirectory($fontFiles);
		$config = $this->getClass(new SystemFontCatalog($fontDirectory))->getConfig();
		$defaultFontData = (new FontVariables())->getDefaults()['fontdata'];

		$this->assertSame(MpdfFontConfigFactory::DEFAULT_FONT_FAMILY, $config['default_font']);
		$this->assertSame(
			$defaultFontData[MpdfFontConfigFactory::DEFAULT_FONT_FAMILY],
			$config['fontdata'][MpdfFontConfigFactory::DEFAULT_FONT_FAMILY],
		);

		$expectedDirectories = array_values(array_unique(array_map(
			static fn (string $relativePath): string => dirname($fontDirectory . '/' . $relativePath),
			$fontFiles,
		)));
		foreach ($expectedDirectories as $expectedDirectory) {
			$this->assertContains($expectedDirectory, $config['fontDir']);
		}

		$this->assertSame($expectedFontData, $config['fontdata'][$expectedRegisteredFamily]);
	}

	public static function provideDiscoveredFontScenarios(): array {
		return [
			'discovered font is registered with explicit variants' => [
				[
					'custom/CustomSans-Regular.ttf',
					'custom/CustomSans-Bold.ttf',
					'custom/CustomSans-Italic.ttf',
					'custom/CustomSans-BoldItalic.ttf',
				],
				'customsans',
				[
					'R' => 'CustomSans-Regular.ttf',
					'B' => 'CustomSans-Bold.ttf',
					'I' => 'CustomSans-Italic.ttf',
					'BI' => 'CustomSans-BoldItalic.ttf',
				],
			],
			'discovered font without variants falls back to regular' => [
				[
					'custom/CustomSans-Regular.ttf',
				],
				'customsans',
				[
					'R' => 'CustomSans-Regular.ttf',
					'B' => 'CustomSans-Regular.ttf',
					'I' => 'CustomSans-Regular.ttf',
					'BI' => 'CustomSans-Regular.ttf',
				],
			],
			'normalized variant names are merged as a system family' => [
				[
					'footer/Footer Serif Roman.ttf',
					'footer/Footer Serif SemiBold.ttf',
					'footer/Footer Serif Oblique.ttf',
					'footer/Footer Serif SemiBold Italic.ttf',
				],
				'footerserif',
				[
					'R' => 'Footer Serif Roman.ttf',
					'B' => 'Footer Serif SemiBold.ttf',
					'I' => 'Footer Serif Oblique.ttf',
					'BI' => 'Footer Serif SemiBold Italic.ttf',
				],
			],
		];
	}

	public function testGetFontFamilyReturnsDynamicDefaultAlias(): void {
		$this->assertSame(MpdfFontConfigFactory::DEFAULT_FONT_FAMILY, $this->getClass()->getFontFamily());
	}

	public function testDiscoveredFontAliasesDoNotOverrideBundledDefinitions(): void {
		$fontDirectory = $this->createDiscoveredFontDirectory([
			'collision/DejaVuSansCondensed-Regular.ttf',
		]);
		$config = $this->getClass(new SystemFontCatalog($fontDirectory))->getConfig();
		$defaultFontData = (new FontVariables())->getDefaults()['fontdata'];

		$this->assertContains($fontDirectory . '/collision', $config['fontDir']);
		$this->assertSame(
			$defaultFontData[MpdfFontConfigFactory::DEFAULT_FONT_FAMILY],
			$config['fontdata'][MpdfFontConfigFactory::DEFAULT_FONT_FAMILY],
		);
	}
}
