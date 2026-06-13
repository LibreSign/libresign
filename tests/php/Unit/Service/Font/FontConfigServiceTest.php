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
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;

final class FontConfigServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAppConfig $appConfig;
	private InMemoryLogger $logger;
	private BundledFontLocator $bundledFontLocator;
	/**
	 * @var list<string>
	 */
	private array $temporaryDirectories = [];

	#[\Override]
	public function setUp(): void {
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->logger = new InMemoryLogger();
		$this->bundledFontLocator = new BundledFontLocator();
	}

	#[\Override]
	public function tearDown(): void {
		foreach ($this->temporaryDirectories as $directory) {
			$this->removeDirectory($directory);
		}

		parent::tearDown();
	}

	private function getClass(): FontConfigService {
		return new FontConfigService($this->appConfig, $this->logger);
	}

	private function createTempDirectory(string $prefix): string {
		$directory = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(8));
		$this->assertTrue(mkdir($directory, 0777, true) || is_dir($directory));
		$this->temporaryDirectories[] = $directory;

		return $directory;
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
		$fontDirectory = $this->createTempDirectory('libresign-font-config-service-');

		foreach ($fontFiles as $fontFile) {
			$sourcePath = $this->bundledFontLocator->requireFontFile($fontFile);
			$destinationPath = $fontDirectory . '/' . $fontFile;
			$this->assertTrue(copy($sourcePath, $destinationPath));
		}

		return $fontDirectory;
	}

	private function createEscapingFontSymlink(string $fontDirectory, string $sourceFontFile): string {
		$outsideDirectory = $this->createTempDirectory('libresign-font-config-outside-');
		$outsideFontPath = $outsideDirectory . '/outside-font.ttf';
		$this->assertTrue(copy($this->bundledFontLocator->requireFontFile($sourceFontFile), $outsideFontPath));

		$symlinkFilename = 'escaped-font.ttf';
		$symlinkPath = $fontDirectory . '/' . $symlinkFilename;
		$this->assertTrue(symlink($outsideFontPath, $symlinkPath));

		return $symlinkFilename;
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

	public function testReturnsDefaultFontFamilyWhenNoConfigurationExists(): void {
		$service = $this->getClass();

		$this->assertNull($service->getConfiguredTemplateFont());
		$this->assertSame(FontConfigService::DEFAULT_FONT_FAMILY, $service->getActiveFontFamily());
		$this->assertSame([], $this->logger->warnings());
	}

	#[DataProvider('provideResolvedFontScenarios')]
	public function testConfiguredFontDefinitionResolvesExpectedVariants(
		string $configPrefix,
		array $availableFontFiles,
		array $settings,
		string $expectedFamily,
		array $expectedVariants,
	): void {
		$fontDirectory = $this->createTempFontDirectory($availableFontFiles);
		$this->setFontConfig($configPrefix, $fontDirectory, $settings);

		$fontDefinition = $this->getClass()->getConfiguredTemplateFont();

		$this->assertNotNull($fontDefinition);
		$this->assertSame($expectedFamily, $fontDefinition->getFamily());
		$this->assertSame($fontDirectory, $fontDefinition->getDirectory());
		$this->assertSame($expectedVariants['regular'], $fontDefinition->getRegular());
		$this->assertSame($expectedVariants['bold'], $fontDefinition->getBold());
		$this->assertSame($expectedVariants['italic'], $fontDefinition->getItalic());
		$this->assertSame($expectedVariants['boldItalic'], $fontDefinition->getBoldItalic());
		$this->assertSame([], $this->logger->warnings());
	}

	public static function provideResolvedFontScenarios(): array {
		return [
			'template config falls back missing variants to regular' => [
				'template_font',
				['DejaVuSansCondensed.ttf'],
				[
					'family' => 'Custom Sans',
					'regular' => 'DejaVuSansCondensed.ttf',
				],
				'customsans',
				[
					'regular' => 'DejaVuSansCondensed.ttf',
					'bold' => 'DejaVuSansCondensed.ttf',
					'italic' => 'DejaVuSansCondensed.ttf',
					'boldItalic' => 'DejaVuSansCondensed.ttf',
				],
			],
			'legacy footer config remains supported' => [
				'footer_font',
				['DejaVuSansCondensed.ttf'],
				[
					'family' => 'Footer Sans',
					'regular' => 'DejaVuSansCondensed.ttf',
				],
				'footersans',
				[
					'regular' => 'DejaVuSansCondensed.ttf',
					'bold' => 'DejaVuSansCondensed.ttf',
					'italic' => 'DejaVuSansCondensed.ttf',
					'boldItalic' => 'DejaVuSansCondensed.ttf',
				],
			],
			'bold italic falls back to configured bold when missing' => [
				'template_font',
				[
					'DejaVuSansCondensed.ttf',
					'DejaVuSansCondensed-Bold.ttf',
					'DejaVuSansCondensed-Oblique.ttf',
				],
				[
					'family' => 'Custom Sans',
					'regular' => 'DejaVuSansCondensed.ttf',
					'bold' => 'DejaVuSansCondensed-Bold.ttf',
					'italic' => 'DejaVuSansCondensed-Oblique.ttf',
				],
				'customsans',
				[
					'regular' => 'DejaVuSansCondensed.ttf',
					'bold' => 'DejaVuSansCondensed-Bold.ttf',
					'italic' => 'DejaVuSansCondensed-Oblique.ttf',
					'boldItalic' => 'DejaVuSansCondensed-Bold.ttf',
				],
			],
			'family normalization removes separators and symbols' => [
				'template_font',
				['DejaVuSansCondensed.ttf'],
				[
					'family' => 'Custom-Sans_Font! 42',
					'regular' => 'DejaVuSansCondensed.ttf',
				],
				'customsansfont42',
				[
					'regular' => 'DejaVuSansCondensed.ttf',
					'bold' => 'DejaVuSansCondensed.ttf',
					'italic' => 'DejaVuSansCondensed.ttf',
					'boldItalic' => 'DejaVuSansCondensed.ttf',
				],
			],
		];
	}

	public function testTemplateFontConfigurationTakesPrecedenceOverLegacyFooterConfiguration(): void {
		$templateFontDirectory = $this->createTempFontDirectory([
			'DejaVuSansCondensed.ttf',
		]);
		$footerFontDirectory = $this->createTempFontDirectory([
			'DejaVuSerifCondensed.ttf',
		]);
		$this->setFontConfig('template_font', $templateFontDirectory, [
			'family' => 'Template Sans',
			'regular' => 'DejaVuSansCondensed.ttf',
		]);
		$this->setFontConfig('footer_font', $footerFontDirectory, [
			'family' => 'Footer Serif',
			'regular' => 'DejaVuSerifCondensed.ttf',
		]);

		$service = $this->getClass();
		$fontDefinition = $service->getConfiguredTemplateFont();

		$this->assertNotNull($fontDefinition);
		$this->assertSame('templatesans', $fontDefinition->getFamily());
		$this->assertSame($templateFontDirectory, $fontDefinition->getDirectory());
		$this->assertSame('DejaVuSansCondensed.ttf', $fontDefinition->getRegular());
		$this->assertSame('templatesans', $service->getActiveFontFamily());
		$this->assertSame([], $this->logger->warnings());
	}

	public function testLegacyFooterFontConfigurationIsUsedWhenTemplateConfigurationIsInvalid(): void {
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

		$service = $this->getClass();
		$fontDefinition = $service->getConfiguredTemplateFont();

		$this->assertNotNull($fontDefinition);
		$this->assertSame('footerserif', $fontDefinition->getFamily());
		$this->assertSame($footerFontDirectory, $fontDefinition->getDirectory());
		$this->assertSame('DejaVuSerifCondensed.ttf', $fontDefinition->getRegular());
		$this->assertSame('footerserif', $service->getActiveFontFamily());

		$warnings = $this->logger->warnings();
		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('configured font file does not exist', $warnings[0]['message']);
		$this->assertSame('template_font_regular', $warnings[0]['context']['configKey'] ?? null);
	}

	#[DataProvider('provideInvalidConfiguredFonts')]
	public function testInvalidConfiguredFontsFallBackAndLogWarning(
		string $configuredRegular,
		string $expectedWarningMessage,
	): void {
		$fontDirectory = $this->createTempFontDirectory([
			'DejaVuSansCondensed.ttf',
		]);
		$this->setFontConfig('template_font', $fontDirectory, [
			'regular' => $configuredRegular,
		]);
		$service = $this->getClass();

		$this->assertNull($service->getConfiguredTemplateFont());
		$this->assertSame(FontConfigService::DEFAULT_FONT_FAMILY, $service->getActiveFontFamily());

		$warnings = $this->logger->warnings();
		$this->assertCount(1, $warnings);
		$this->assertStringContainsString($expectedWarningMessage, $warnings[0]['message']);
		$this->assertSame('template_font_regular', $warnings[0]['context']['configKey'] ?? null);
	}

	public static function provideInvalidConfiguredFonts(): array {
		return [
			'missing required file' => [
				'',
				'required font file is missing',
			],
			'path traversal' => [
				'../DejaVuSansCondensed.ttf',
				'font file path is not allowed',
			],
			'url-like path' => [
				'https://example.test/font.ttf',
				'font file path is not allowed',
			],
			'unsupported extension' => [
				'DejaVuSansCondensed.woff',
				'font file extension is not supported',
			],
			'missing file' => [
				'Missing.ttf',
				'configured font file does not exist',
			],
		];
	}

	#[DataProvider('provideInvalidConfigurationGroups')]
	public function testInvalidConfigurationGroupFallsBackAndLogsWarning(array $settings): void {
		$fontDirectory = $this->createTempFontDirectory([
			'DejaVuSansCondensed.ttf',
		]);
		$this->setFontConfig('template_font', $fontDirectory, $settings);
		$service = $this->getClass();

		$this->assertNull($service->getConfiguredTemplateFont());
		$this->assertSame(FontConfigService::DEFAULT_FONT_FAMILY, $service->getActiveFontFamily());

		$warnings = $this->logger->warnings();
		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('missing valid directory or family', $warnings[0]['message']);
		$this->assertSame('template_font_family', $warnings[0]['context']['familyKey'] ?? null);
		$this->assertSame('template_font_dir', $warnings[0]['context']['directoryKey'] ?? null);
	}

	public static function provideInvalidConfigurationGroups(): array {
		return [
			'missing family' => [[
				'family' => '',
			]],
			'missing directory' => [[
				'dir' => '',
			]],
			'non-existent directory' => [[
				'dir' => sys_get_temp_dir() . '/libresign-font-config-missing-' . bin2hex(random_bytes(4)),
			]],
		];
	}

	public function testSymlinkEscapingConfiguredDirectoryIsRejected(): void {
		$fontDirectory = $this->createTempFontDirectory([
			'DejaVuSansCondensed.ttf',
		]);
		$escapedFontFilename = $this->createEscapingFontSymlink($fontDirectory, 'DejaVuSansCondensed.ttf');
		$this->setFontConfig('template_font', $fontDirectory, [
			'regular' => $escapedFontFilename,
		]);
		$service = $this->getClass();

		$this->assertNull($service->getConfiguredTemplateFont());
		$this->assertSame(FontConfigService::DEFAULT_FONT_FAMILY, $service->getActiveFontFamily());

		$warnings = $this->logger->warnings();
		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('configured font file escapes the configured directory', $warnings[0]['message']);
		$this->assertSame('template_font_regular', $warnings[0]['context']['configKey'] ?? null);
	}

	#[DataProvider('provideInvalidOptionalVariants')]
	public function testInvalidOptionalVariantFallsBackToValidVariantsAndLogsWarning(
		array $availableFontFiles,
		array $settings,
		array $expectedVariants,
		string $expectedWarningConfigKey,
	): void {
		$fontDirectory = $this->createTempFontDirectory($availableFontFiles);
		$this->setFontConfig('template_font', $fontDirectory, $settings);
		$service = $this->getClass();

		$fontDefinition = $service->getConfiguredTemplateFont();

		$this->assertNotNull($fontDefinition);
		$this->assertSame('customsans', $fontDefinition->getFamily());
		$this->assertSame($expectedVariants['regular'], $fontDefinition->getRegular());
		$this->assertSame($expectedVariants['bold'], $fontDefinition->getBold());
		$this->assertSame($expectedVariants['italic'], $fontDefinition->getItalic());
		$this->assertSame($expectedVariants['boldItalic'], $fontDefinition->getBoldItalic());
		$this->assertSame('customsans', $service->getActiveFontFamily());

		$warnings = $this->logger->warnings();
		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('configured font file does not exist', $warnings[0]['message']);
		$this->assertSame($expectedWarningConfigKey, $warnings[0]['context']['configKey'] ?? null);
	}

	public static function provideInvalidOptionalVariants(): array {
		return [
			'invalid bold falls back to regular' => [
				['DejaVuSansCondensed.ttf'],
				['bold' => 'Missing.ttf'],
				[
					'regular' => 'DejaVuSansCondensed.ttf',
					'bold' => 'DejaVuSansCondensed.ttf',
					'italic' => 'DejaVuSansCondensed.ttf',
					'boldItalic' => 'DejaVuSansCondensed.ttf',
				],
				'template_font_bold',
			],
			'invalid italic falls back to regular' => [
				['DejaVuSansCondensed.ttf'],
				['italic' => 'Missing.ttf'],
				[
					'regular' => 'DejaVuSansCondensed.ttf',
					'bold' => 'DejaVuSansCondensed.ttf',
					'italic' => 'DejaVuSansCondensed.ttf',
					'boldItalic' => 'DejaVuSansCondensed.ttf',
				],
				'template_font_italic',
			],
			'invalid bold italic falls back to configured bold' => [
				[
					'DejaVuSansCondensed.ttf',
					'DejaVuSansCondensed-Bold.ttf',
				],
				[
					'bold' => 'DejaVuSansCondensed-Bold.ttf',
					'bold_italic' => 'Missing.ttf',
				],
				[
					'regular' => 'DejaVuSansCondensed.ttf',
					'bold' => 'DejaVuSansCondensed-Bold.ttf',
					'italic' => 'DejaVuSansCondensed.ttf',
					'boldItalic' => 'DejaVuSansCondensed-Bold.ttf',
				],
				'template_font_bold_italic',
			],
		];
	}
}
