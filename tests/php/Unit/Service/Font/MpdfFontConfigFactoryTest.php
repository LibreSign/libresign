<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service\Font;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Font\BundledFontLocator;
use OCA\Libresign\Service\Font\FontConfigService;
use OCA\Libresign\Service\Font\MpdfFontConfigFactory;
use OCA\Libresign\Vendor\Mpdf\Config\FontVariables;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;

final class MpdfFontConfigFactoryTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAppConfig $appConfig;
	private BundledFontLocator $bundledFontLocator;
	/**
	 * @var list<string>
	 */
	private array $temporaryDirectories = [];

	#[\Override]
	public function setUp(): void {
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->bundledFontLocator = new BundledFontLocator();
	}

	#[\Override]
	public function tearDown(): void {
		foreach ($this->temporaryDirectories as $directory) {
			$this->removeDirectory($directory);
		}

		parent::tearDown();
	}

	private function getClass(): MpdfFontConfigFactory {
		return new MpdfFontConfigFactory(
			new FontConfigService($this->appConfig, new NullLogger()),
			$this->bundledFontLocator,
		);
	}

	/**
	 * @param array<string, string> $settings
	 */
	private function setFontConfig(string $configPrefix, string $fontDirectory, array $settings = []): void {
		$defaults = [
			'family' => 'Custom Sans',
			'dir' => $fontDirectory,
			'regular' => 'DejaVuSansCondensed.ttf',
		];

		foreach (array_merge($defaults, $settings) as $suffix => $value) {
			$this->appConfig->setValueString(Application::APP_ID, $configPrefix . '_' . $suffix, $value);
		}
	}

	/**
	 * @param list<string> $fontFiles
	 */
	private function createTempFontDirectory(array $fontFiles): string {
		$fontDirectory = sys_get_temp_dir() . '/libresign-mpdf-font-factory-' . bin2hex(random_bytes(8));
		$this->assertTrue(mkdir($fontDirectory, 0777, true) || is_dir($fontDirectory));
		$this->temporaryDirectories[] = $fontDirectory;

		foreach ($fontFiles as $fontFile) {
			$sourcePath = $this->bundledFontLocator->requireFontFile($fontFile);
			$destinationPath = $fontDirectory . '/' . $fontFile;
			$this->assertTrue(copy($sourcePath, $destinationPath));
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

		$this->assertSame(FontConfigService::DEFAULT_FONT_FAMILY, $config['default_font']);
		$this->assertNotEmpty($config['fontDir']);
		foreach ($config['fontDir'] as $fontDirectory) {
			$this->assertDirectoryExists($fontDirectory);
		}
		$this->assertArrayHasKey(FontConfigService::DEFAULT_FONT_FAMILY, $config['fontdata']);
		$this->assertSame($defaultFontData[FontConfigService::DEFAULT_FONT_FAMILY], $config['fontdata'][FontConfigService::DEFAULT_FONT_FAMILY]);
	}

	#[DataProvider('provideConfiguredFontScenarios')]
	public function testConfiguredFontScenariosAffectMpdfConfig(
		string $configPrefix,
		array $availableFontFiles,
		array $settings,
		string $expectedDefaultFont,
		?string $expectedRegisteredFamily,
		?array $expectedFontData,
	): void {
		$fontDirectory = $this->createTempFontDirectory($availableFontFiles);
		$this->setFontConfig($configPrefix, $fontDirectory, $settings);

		$config = $this->getClass()->getConfig();

		$this->assertSame($expectedDefaultFont, $config['default_font']);
		if ($expectedRegisteredFamily === null || $expectedFontData === null) {
			$this->assertNotContains($fontDirectory, $config['fontDir']);
			$this->assertArrayNotHasKey('customsans', $config['fontdata']);

			return;
		}

		$this->assertSame($fontDirectory, $config['fontDir'][0]);
		$this->assertSame($expectedFontData, $config['fontdata'][$expectedRegisteredFamily]);
	}

	public static function provideConfiguredFontScenarios(): array {
		return [
			'template font is registered with explicit variants' => [
				'template_font',
				[
					'DejaVuSansCondensed.ttf',
					'DejaVuSansCondensed-Bold.ttf',
					'DejaVuSansCondensed-Oblique.ttf',
					'DejaVuSansCondensed-BoldOblique.ttf',
				],
				[
					'family' => 'Custom Sans',
					'regular' => 'DejaVuSansCondensed.ttf',
					'bold' => 'DejaVuSansCondensed-Bold.ttf',
					'italic' => 'DejaVuSansCondensed-Oblique.ttf',
					'bold_italic' => 'DejaVuSansCondensed-BoldOblique.ttf',
				],
				'customsans',
				'customsans',
				[
					'R' => 'DejaVuSansCondensed.ttf',
					'B' => 'DejaVuSansCondensed-Bold.ttf',
					'I' => 'DejaVuSansCondensed-Oblique.ttf',
					'BI' => 'DejaVuSansCondensed-BoldOblique.ttf',
				],
			],
			'legacy footer font config is still registered' => [
				'footer_font',
				['DejaVuSansCondensed.ttf'],
				[
					'family' => 'Footer Sans',
					'regular' => 'DejaVuSansCondensed.ttf',
				],
				'footersans',
				'footersans',
				[
					'R' => 'DejaVuSansCondensed.ttf',
					'B' => 'DejaVuSansCondensed.ttf',
					'I' => 'DejaVuSansCondensed.ttf',
					'BI' => 'DejaVuSansCondensed.ttf',
				],
			],
			'invalid optional bold falls back to regular for mpdf' => [
				'template_font',
				['DejaVuSansCondensed.ttf'],
				[
					'family' => 'Custom Sans',
					'regular' => 'DejaVuSansCondensed.ttf',
					'bold' => 'Missing.ttf',
				],
				'customsans',
				'customsans',
				[
					'R' => 'DejaVuSansCondensed.ttf',
					'B' => 'DejaVuSansCondensed.ttf',
					'I' => 'DejaVuSansCondensed.ttf',
					'BI' => 'DejaVuSansCondensed.ttf',
				],
			],
			'invalid configured font falls back to defaults' => [
				'template_font',
				['DejaVuSansCondensed.ttf'],
				[
					'family' => 'Custom Sans',
					'regular' => 'Missing.ttf',
				],
				FontConfigService::DEFAULT_FONT_FAMILY,
				null,
				null,
			],
		];
	}

	public function testGetFontFamilyReturnsResolvedConfiguredTemplateFamily(): void {
		$fontDirectory = $this->createTempFontDirectory([
			'DejaVuSansCondensed.ttf',
		]);
		$this->setFontConfig('template_font', $fontDirectory, [
			'family' => 'Custom Sans',
			'regular' => 'DejaVuSansCondensed.ttf',
		]);

		$this->assertSame('customsans', $this->getClass()->getFontFamily());
	}

	public function testGetFontFamilyFallsBackToLegacyFooterConfigurationWhenTemplateConfigurationIsInvalid(): void {
		$templateFontDirectory = $this->createTempFontDirectory([
			'DejaVuSansCondensed.ttf',
		]);
		$footerFontDirectory = $this->createTempFontDirectory([
			'DejaVuSerifCondensed.ttf',
		]);
		$this->setFontConfig('template_font', $templateFontDirectory, [
			'family' => 'Template Sans',
			'regular' => 'Missing.ttf',
		]);
		$this->setFontConfig('footer_font', $footerFontDirectory, [
			'family' => 'Footer Serif',
			'regular' => 'DejaVuSerifCondensed.ttf',
		]);

		$this->assertSame('footerserif', $this->getClass()->getFontFamily());
	}

	public function testConfiguredFontDirectoryIsPrependedOnlyOnceWhenItMatchesBundledDirectory(): void {
		$bundledFontDirectory = dirname($this->bundledFontLocator->requireFontFile('DejaVuSansCondensed.ttf'));
		$this->setFontConfig('template_font', $bundledFontDirectory, [
			'family' => 'Bundled Sans',
			'regular' => 'DejaVuSansCondensed.ttf',
		]);

		$config = $this->getClass()->getConfig();
		$matchingDirectories = array_values(array_filter(
			$config['fontDir'],
			static fn (string $directory): bool => $directory === $bundledFontDirectory,
		));

		$this->assertCount(1, $matchingDirectories);
		$this->assertSame($bundledFontDirectory, $config['fontDir'][0]);
		$this->assertSame('bundledsans', $config['default_font']);
		$this->assertSame([
			'R' => 'DejaVuSansCondensed.ttf',
			'B' => 'DejaVuSansCondensed.ttf',
			'I' => 'DejaVuSansCondensed.ttf',
			'BI' => 'DejaVuSansCondensed.ttf',
		], $config['fontdata']['bundledsans']);
	}
}
