<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Handler;

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\TemplateVariables;
use OCA\Libresign\Service\File\Pdf\PdfMetadataExtractor;
use OCA\Libresign\Service\Font\BundledFontLocator;
use OCA\Libresign\Service\Font\FontConfigService;
use OCA\Libresign\Service\Font\MpdfFontConfigFactory;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class FooterHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAppConfig $appConfig;
	private PdfMetadataExtractor $pdfMetadataExtractor;
	private IURLGenerator $urlGenerator;
	private IL10N $l10n;
	private IFactory $l10nFactory;
	private ITempManager $tempManager;
	private FooterHandler $footerHandler;
	private BundledFontLocator $bundledFontLocator;

	#[\Override]
	public function setUp(): void {
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->pdfMetadataExtractor = $this->createStub(PdfMetadataExtractor::class);
		$this->urlGenerator = $this->createStub(IURLGenerator::class);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
		$this->l10nFactory = \OCP\Server::get(IFactory::class);
		$this->bundledFontLocator = new BundledFontLocator();
	}

	private function getClass(
		?PdfMetadataExtractor $pdfMetadataExtractor = null,
		?IURLGenerator $urlGenerator = null,
		?MpdfFontConfigFactory $mpdfFontConfigFactory = null,
	): FooterHandler {
		$templateVars = new TemplateVariables($this->l10n);
		$this->footerHandler = new FooterHandler(
			$this->appConfig,
			$pdfMetadataExtractor ?? $this->pdfMetadataExtractor,
			$urlGenerator ?? $this->urlGenerator,
			$this->l10n,
			$this->l10nFactory,
			$this->tempManager,
			$templateVars,
			$mpdfFontConfigFactory ?? $this->getMpdfFontConfigFactory(),
		);
		return $this->footerHandler;
	}

	private function getMpdfFontConfigFactory(): MpdfFontConfigFactory {
		return new MpdfFontConfigFactory(
			new FontConfigService($this->appConfig, new NullLogger()),
			$this->bundledFontLocator,
		);
	}

	private function getBundledMpdfFontsDirectory(string $requiredFontFile = 'DejaVuSansCondensed.ttf'): string {
		return dirname($this->bundledFontLocator->requireFontFile($requiredFontFile));
	}

	/**
	 * @param array<string, string> $settings
	 */
	private function setTemplateFontConfig(array $settings = []): void {
		$defaults = [
			'family' => 'Custom Sans',
			'dir' => $this->getBundledMpdfFontsDirectory(),
			'regular' => 'DejaVuSansCondensed.ttf',
		];

		foreach (array_merge($defaults, $settings) as $suffix => $value) {
			$this->appConfig->setValueString(Application::APP_ID, 'template_font_' . $suffix, $value);
		}
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function getStubMpdfFontConfigFactory(
		array $config = [],
		string $fontFamily = FontConfigService::DEFAULT_FONT_FAMILY,
	): MpdfFontConfigFactory {
		$defaultConfig = [
			'fontDir' => [$this->getBundledMpdfFontsDirectory()],
			'fontdata' => [
				$fontFamily => [
					'R' => 'DejaVuSansCondensed.ttf',
					'B' => 'DejaVuSansCondensed-Bold.ttf',
					'I' => 'DejaVuSansCondensed-Oblique.ttf',
					'BI' => 'DejaVuSansCondensed-BoldOblique.ttf',
				],
			],
			'default_font' => $fontFamily,
		];

		return new class(array_merge($defaultConfig, $config), $fontFamily) extends MpdfFontConfigFactory {
			/**
			 * @param array<string, mixed> $config
			 */
			public function __construct(
				private array $config,
				private string $fontFamily,
			) {
			}

			#[\Override]
			public function getConfig(): array {
				return $this->config;
			}

			#[\Override]
			public function getFontFamily(): string {
				return $this->fontFamily;
			}
		};
	}

	public function testGetFooterWithoutValidationSite(): void {
		$this->appConfig->setValueBool(Application::APP_ID, 'add_footer', false);
		$dimensions = [['w' => 595, 'h' => 842]];
		$this->l10n = $this->l10nFactory->get(Application::APP_ID);
		$actual = $this->getClass()
			->setTemplateVar('uuid', 'test-uuid')
			->getFooter($dimensions);
		$this->assertEmpty($actual);
	}

	public function testGetFooterReturnsEmptyStringWhenDimensionsAreEmpty(): void {
		$this->appConfig->setValueBool(Application::APP_ID, 'add_footer', true);
		$this->appConfig->setValueBool(Application::APP_ID, 'write_qrcode_on_footer', false);
		$this->appConfig->setValueString(Application::APP_ID, 'footer_template', '<div>{{ signedBy|raw }}</div>');
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$this->assertSame('', $this->getClass()->getFooter([]));
	}

	#[DataProvider('dataGetFooterWithSuccess')]
	public function testGetFooterWithSuccess(string $language, array $settings, array $expected): void {
		foreach ($settings as $key => $value) {
			switch (gettype($value)) {
				case 'boolean':
					$this->appConfig->setValueBool(Application::APP_ID, $key, $value);
					break;
				case 'string':
					$this->appConfig->setValueString(Application::APP_ID, $key, $value);
					break;
			}
		}

		$dimensions = [
			[
				'w' => 842,
				'h' => 595,
			],
		];

		$this->l10n = $this->l10nFactory->get(Application::APP_ID, $language);

		$pdf = $this->getClass()
			->setTemplateVar('uuid', 'uuid')
			->getFooter($dimensions);
		if ($settings['add_footer']) {
			$actual = $this->extractPdfContent(
				$pdf,
				array_keys($expected),
				$this->l10nFactory->getLanguageDirection($language)
			);
			if ($settings['write_qrcode_on_footer']) {
				$this->assertNotEmpty($actual['qrcode'], 'Invalid qrcode content');
				unset($actual['qrcode'], $expected['qrcode']);
			}
			$this->assertEquals($expected, $actual);
		} else {
			$this->assertEmpty($pdf);
		}
	}

	public static function dataGetFooterWithSuccess(): array {
		$data = [
			'without_footer' => [
				'en',
				['add_footer' => false,], []
			],
			'en_with_more_fields' => [
				'en',
				[
					'add_footer' => true,
					'validation_site' => 'http://test.coop',
					'write_qrcode_on_footer' => true,
					'footer_link_to_site' => 'https://libresign.coop',
					'footer_signed_by' => 'Digital signed by LibreSign.',
					'footer_validate_in' => 'Validate in %s.',
					'footer_template' => <<<'HTML'
						<div style="font-size:8px;" dir="{{ direction }}">
						qrcodeSize:{{ qrcodeSize }}<br />
						signedBy:{{ signedBy|raw }}<br />
						validateIn:{{ validateIn|raw }}<br />
						qrcode:{{ qrcode }}
						</div>
						HTML,
				],
				[
					'qrcode' => 'dummy value',
					'qrcodeSize' => '108',
					'signedBy' => 'Digital signed by LibreSign.',
					'validateIn' => 'Validate in %s.',
				]
			],
			'en' => [
				'en',
				[
					'add_footer' => true,
					'validation_site' => 'http://test.coop',
					'write_qrcode_on_footer' => false,
					'footer_link_to_site' => 'https://libresign.coop',
					'footer_signed_by' => 'Digital signed by LibreSign.',
					'footer_validate_in' => 'Validate in %s.',
					'footer_template' => <<<'HTML'
						<div style="font-size:8px;" dir="{{ direction }}">
						signedBy:{{ signedBy|raw }}<br />
						validateIn:{{ validateIn|raw }}<br />
						</div>
						HTML,
				],
				[
					'signedBy' => 'Digital signed by LibreSign.',
					'validateIn' => 'Validate in %s.',
				]
			],
			'fr' => [
				'fr',
				[
					'add_footer' => true,
					'validation_site' => 'http://test.coop',
					'write_qrcode_on_footer' => false,
					'footer_link_to_site' => 'https://libresign.coop',
					'footer_signed_by' => 'Signé numériquement avec LibreSign.',
					'footer_validate_in' => 'Validate in %s',
					'footer_template' => <<<'HTML'
						<div style="font-size:8px;" dir="{{ direction }}">
						signedBy:{{ signedBy|raw }}<br />
						validateIn:{{ validateIn|raw }}<br />
						</div>
						HTML,
				],
				[
					'signedBy' => 'Signé numériquement avec LibreSign.',
					'validateIn' => 'Validate in %s',
				]
			],
			'el' => [
				'el',
				[
					'add_footer' => true,
					'validation_site' => 'http://test.coop',
					'write_qrcode_on_footer' => false,
					'footer_link_to_site' => 'https://libresign.coop',
					'footer_signed_by' => 'Το αρχείο υπάρχει',
					'footer_validate_in' => 'Επικυρώστε στο %s.',
					'footer_template' => <<<'HTML'
						<div style="font-size:8px;" dir="{{ direction }}">
						signedBy:{{ signedBy|raw }}<br />
						validateIn:{{ validateIn|raw }}<br />
						</div>
						HTML,
				],
				[
					'signedBy' => 'Το αρχείο υπάρχει',
					'validateIn' => 'Επικυρώστε στο %s.',
				]
			],
			'he' => [
				'he',
				[
					'add_footer' => true,
					'validation_site' => 'http://test.coop',
					'write_qrcode_on_footer' => false,
					'footer_link_to_site' => 'https://libresign.coop',
					'footer_signed_by' => 'אין המלצות. נא להתחיל להקליד.',
					'footer_validate_in' => 'אמת ב- %s.',
					'footer_template' => <<<'HTML'
						<div style="font-size:8px;" dir="{{ direction }}">
						signedBy:{{ signedBy|raw }}<br />
						validateIn:{{ validateIn|raw }}<br />
						</div>
						HTML,
				],
				[
					'signedBy' => 'אין המלצות. נא להתחיל להקליד.',
					'validateIn' => 'אמת ב- %s.',
				]
			],
		];

		// LTR langages was ignored at CI because the returned text is flipped by MPDF
		return array_filter($data, fn ($key) => !in_array($key, ['he']), ARRAY_FILTER_USE_KEY);
	}

	#[DataProvider('dataGetMetadata')]
	public function testGetMetadataUsesStoredMetadataOrExtractorFallback(
		?array $storedMetadata,
		bool $shouldUseExtractor,
		array $expectedMetadata,
	): void {
		$file = $this->createStub(File::class);
		$fileEntity = new \OCA\Libresign\Db\File();
		if ($storedMetadata !== null) {
			$fileEntity->setMetadata($storedMetadata);
		}

		$pdfMetadataExtractor = $this->createMock(PdfMetadataExtractor::class);
		if ($shouldUseExtractor) {
			$pdfMetadataExtractor->expects($this->once())
				->method('setFile')
				->with($file)
				->willReturnSelf();
			$pdfMetadataExtractor->expects($this->once())
				->method('getPageDimensions')
				->willReturn($expectedMetadata);
		} else {
			$pdfMetadataExtractor->expects($this->never())
				->method('setFile');
			$pdfMetadataExtractor->expects($this->never())
				->method('getPageDimensions');
		}

		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$this->assertSame($expectedMetadata, $this->getClass($pdfMetadataExtractor)->getMetadata($file, $fileEntity));
	}

	public static function dataGetMetadata(): array {
		return [
			'stored metadata is reused' => [
				['d' => [['w' => 595, 'h' => 842]]],
				false,
				['d' => [['w' => 595, 'h' => 842]]],
			],
			'missing metadata falls back to extractor' => [
				null,
				true,
				['d' => [['w' => 600, 'h' => 800]]],
			],
			'metadata without dimensions key falls back to extractor' => [
				['page' => 1],
				true,
				['d' => [['w' => 700, 'h' => 900]]],
			],
		];
	}

	private function extractPdfContent(string $content, array $keys, string $direction): array {
		$this->assertNotEmpty($content, 'Empty PDF file');
		$this->assertNotEmpty($keys, 'Is necessary to send a not empty array of fields to search at PDF file');
		$parser = new \Smalot\PdfParser\Parser();
		$pdf = $parser->parseContent($content);
		$text = $pdf->getText();
		$this->assertNotEmpty($text, 'PDF without text');
		$content = explode("\n", (string)$text);
		$this->assertNotEmpty($content, 'PDF without any row');
		$content = array_map(fn ($row) => str_getcsv($row, ':', '"', '\\'), $content);

		// Necessary flip key/value when the language is LTR
		$columnKey = $direction === 'rtl' ? 1 : 0;
		$columnValue = $direction === 'rtl' ? 0 : 1;

		$content = array_filter($content, fn ($row) => in_array($row[$columnKey], $keys));
		$this->assertNotEmpty($content, 'Fields not found at PDF file');
		return array_column($content, $columnValue, $columnKey);
	}

	public function testGetFooterWithoutUuid(): void {
		$this->appConfig->setValueBool(Application::APP_ID, 'add_footer', true);
		$this->appConfig->setValueBool(Application::APP_ID, 'write_qrcode_on_footer', true);
		$this->appConfig->setValueString(Application::APP_ID, 'footer_template', '<div>{{ signedBy|raw }}</div>');

		$dimensions = [['w' => 595, 'h' => 100]];
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$pdf = $this->getClass()->getFooter($dimensions);
		$this->assertNotEmpty($pdf);

		$parser = new \Smalot\PdfParser\Parser();
		$pdfParsed = $parser->parseContent($pdf);
		$text = $pdfParsed->getText();
		$this->assertNotEmpty($text);
	}

	public function testInvalidTwigTemplateThrowsLibresignException(): void {
		$this->appConfig->setValueBool(Application::APP_ID, 'add_footer', true);
		$this->appConfig->setValueBool(Application::APP_ID, 'write_qrcode_on_footer', false);
		$this->appConfig->setValueString(Application::APP_ID, 'footer_template', '{{ signedBy');
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$this->expectException(LibresignException::class);

		$this->getClass()->getFooter([['w' => 595, 'h' => 100]]);
	}

	public function testBoldFooterRendersWithBundledSansFont(): void {
		$this->appConfig->setValueBool(Application::APP_ID, 'add_footer', true);
		$this->appConfig->setValueBool(Application::APP_ID, 'write_qrcode_on_footer', false);
		$this->appConfig->setValueString(
			Application::APP_ID,
			'footer_template',
			'<div><strong>{{ signedBy|raw }}</strong> {{ uuid }}</div>'
		);

		$dimensions = [['w' => 595, 'h' => 100]];
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$pdf = $this->getClass()
			->setTemplateVar('uuid', 'test-uuid')
			->setTemplateVar('signedBy', 'Signed by LibreSign')
			->getFooter($dimensions);

		$this->assertNotEmpty($pdf);
		$parser = new \Smalot\PdfParser\Parser();
		$pdfParsed = $parser->parseContent($pdf);
		$text = $pdfParsed->getText();

		$this->assertStringContainsString('Signed by LibreSign', $text);
		$this->assertStringContainsString('test-uuid', $text);
	}

	public function testStrongTagDoesNotBreakFooterWhenConfiguredBoldVariantIsInvalid(): void {
		$this->appConfig->setValueBool(Application::APP_ID, 'add_footer', true);
		$this->appConfig->setValueBool(Application::APP_ID, 'write_qrcode_on_footer', false);
		$this->appConfig->setValueString(
			Application::APP_ID,
			'footer_template',
			'<div><strong>{{ signedBy|raw }}</strong> {{ uuid }}</div>'
		);
		$this->setTemplateFontConfig([
			'bold' => 'Missing.ttf',
		]);
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$pdf = $this->getClass()
			->setTemplateVar('uuid', 'test-uuid')
			->setTemplateVar('signedBy', 'Signed by LibreSign')
			->getFooter([['w' => 595, 'h' => 100]]);

		$this->assertNotEmpty($pdf);
		$parser = new \Smalot\PdfParser\Parser();
		$pdfParsed = $parser->parseContent($pdf);
		$text = $pdfParsed->getText();

		$this->assertStringContainsString('Signed by LibreSign', $text);
		$this->assertStringContainsString('test-uuid', $text);
	}

	public function testCustomValidationSiteNotOverwritten(): void {
		$this->appConfig->setValueBool(Application::APP_ID, 'add_footer', true);
		$this->appConfig->setValueString(Application::APP_ID, 'validation_site', 'https://default.site');
		$this->appConfig->setValueString(Application::APP_ID, 'footer_template', '<div>{{ validationSite }}</div>');

		$dimensions = [['w' => 595, 'h' => 100]];
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$pdf = $this->getClass()
			->setTemplateVar('validationSite', 'https://custom.validation.site')
			->getFooter($dimensions);

		$this->assertNotEmpty($pdf);
		$parser = new \Smalot\PdfParser\Parser();
		$pdfParsed = $parser->parseContent($pdf);
		$text = $pdfParsed->getText();
		$this->assertStringContainsString('https://custom.validation.site', $text);
		$this->assertStringNotContainsString('https://default.site', $text);
	}

	public function testDefaultTemplateUsesBundledSansFontFamily(): void {
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$template = $this->getClass()->getDefaultTemplate();

		$this->assertStringContainsString("font-family: {{ fontFamily|default('dejavusanscondensed')|e('css') }}, sans-serif;", $template);
	}

	public function testManualFontFamilyCannotOverrideResolvedFontFamily(): void {
		$this->appConfig->setValueBool(Application::APP_ID, 'add_footer', true);
		$this->appConfig->setValueBool(Application::APP_ID, 'write_qrcode_on_footer', false);
		$this->appConfig->setValueString(Application::APP_ID, 'footer_template', '<div>{{ fontFamily }}</div>');
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');
		$fontFactory = $this->getStubMpdfFontConfigFactory(fontFamily: 'resolvedsafealias');

		$pdf = $this->getClass(null, null, $fontFactory)
			->setTemplateVar('fontFamily', 'manualoverride')
			->getFooter([['w' => 595, 'h' => 100]]);

		$this->assertNotEmpty($pdf);
		$parser = new \Smalot\PdfParser\Parser();
		$pdfParsed = $parser->parseContent($pdf);
		$text = $pdfParsed->getText();

		$this->assertStringContainsString('resolvedsafealias', $text);
		$this->assertStringNotContainsString('manualoverride', $text);
	}

	public function testFactoryConfigCannotOverrideFooterManagedMpdfSettings(): void {
		$this->appConfig->setValueBool(Application::APP_ID, 'add_footer', true);
		$this->appConfig->setValueBool(Application::APP_ID, 'write_qrcode_on_footer', false);
		$this->appConfig->setValueString(
			Application::APP_ID,
			'footer_template',
			'<div style="font-family: {{ fontFamily }};"><strong>{{ signedBy|raw }}</strong></div>'
		);
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');
		$fontConfig = [
			'tempDir' => __FILE__,
			'format' => ['not', 'used'],
			'orientation' => 'L',
			'margin_left' => 999,
			'margin_right' => 999,
			'unexpected' => 'value',
		];

		$pdf = $this->getClass(null, null, $this->getStubMpdfFontConfigFactory($fontConfig, 'resolvedsafealias'))
			->setTemplateVar('signedBy', 'Signed by LibreSign')
			->getFooter([['w' => 595, 'h' => 100]]);

		$this->assertNotEmpty($pdf);
		$parser = new \Smalot\PdfParser\Parser();
		$pdfParsed = $parser->parseContent($pdf);
		$text = $pdfParsed->getText();

		$this->assertStringContainsString('Signed by LibreSign', $text);
	}

	public function testFooterFontServicesResolveFromContainer(): void {
		$this->assertInstanceOf(LoggerInterface::class, \OCP\Server::get(LoggerInterface::class));
		$this->assertInstanceOf(BundledFontLocator::class, \OCP\Server::get(BundledFontLocator::class));
		$this->assertInstanceOf(FontConfigService::class, \OCP\Server::get(FontConfigService::class));
		$this->assertInstanceOf(MpdfFontConfigFactory::class, \OCP\Server::get(MpdfFontConfigFactory::class));
		$this->assertInstanceOf(FooterHandler::class, \OCP\Server::get(FooterHandler::class));
	}

	public function testGetTemplateReturnsCustomTemplate(): void {
		$customTemplate = '<div>Custom footer template {{ uuid }}</div>';
		$this->appConfig->setValueString(Application::APP_ID, 'footer_template', $customTemplate);
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$template = $this->getClass()->getTemplate();

		$this->assertSame($customTemplate, $template);
	}

	#[DataProvider('dataTemplateFallbackValues')]
	public function testGetTemplateReturnsDefaultWhenTemplateIsUnsetOrEmpty(?string $configuredTemplate): void {
		if ($configuredTemplate === null) {
			$this->appConfig->deleteKey(Application::APP_ID, 'footer_template');
		} else {
			$this->appConfig->setValueString(Application::APP_ID, 'footer_template', $configuredTemplate);
		}
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$template = $this->getClass()->getTemplate();

		$this->assertNotEmpty($template);
		$defaultTemplate = file_get_contents(__DIR__ . '/../../../../lib/Handler/Templates/footer.twig');
		$this->assertSame($defaultTemplate, $template);
	}

	public static function dataTemplateFallbackValues(): array {
		return [
			'not configured' => [null],
			'configured as empty string' => [''],
		];
	}

	public function testGetTemplateVariablesMetadata(): void {
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');
		$metadata = $this->getClass()->getTemplateVariablesMetadata();

		$this->assertIsArray($metadata);
		$this->assertCount(10, $metadata);
		$this->assertArrayHasKey('direction', $metadata);
		$this->assertArrayHasKey('fontFamily', $metadata);
		$this->assertArrayHasKey('uuid', $metadata);
		$this->assertArrayHasKey('signedBy', $metadata);
		$this->assertSame('string', $metadata['direction']['type']);
		$this->assertSame('string', $metadata['fontFamily']['type']);
		$this->assertSame('string', $metadata['uuid']['type']);
		$this->assertArrayHasKey('default', $metadata['signedBy']);
	}

	#[DataProvider('dataAccentedCharactersInFooter')]
	public function testAccentedCharactersInFooterVariablesAreRenderedCorrectly(
		string $testName,
		string $signedByText,
		array $expectedSubstrings,
		array $forbiddenSubstrings,
	): void {
		$this->appConfig->setValueBool(Application::APP_ID, 'add_footer', true);
		$this->appConfig->setValueBool(Application::APP_ID, 'write_qrcode_on_footer', false);
		$this->appConfig->deleteKey(Application::APP_ID, 'footer_template');

		$dimensions = [['w' => 595, 'h' => 100]];
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$pdf = $this->getClass()
			->setTemplateVar('uuid', 'test-uuid')
			->setTemplateVar('signedBy', $signedByText)
			->setTemplateVar('linkToSite', 'https://libresign.coop')
			->getFooter($dimensions);

		$this->assertNotEmpty($pdf);

		$parser = new \Smalot\PdfParser\Parser();
		$pdfParsed = $parser->parseContent($pdf);
		$text = $pdfParsed->getText();

		foreach ($expectedSubstrings as $expected) {
			$alternatives = is_array($expected) ? $expected : [$expected];
			$matched = false;
			foreach ($alternatives as $alternative) {
				if (str_contains($text, $alternative)) {
					$matched = true;
					break;
				}
			}

			$this->assertTrue(
				$matched,
				"Expected to find one of '" . implode("', '", $alternatives) . "' for test: {$testName}"
			);
		}

		foreach ($forbiddenSubstrings as $forbidden) {
			$this->assertStringNotContainsString($forbidden, $text, "Should not find '{$forbidden}' for test: {$testName}");
		}
	}

	public static function dataAccentedCharactersInFooter(): array {
		return [
			'French accents' => [
				'testName' => 'French accents',
				'signedByText' => 'Signé numériquement par LibreSign',
				'expectedSubstrings' => ['Signé', 'numériquement'],
				'forbiddenSubstrings' => ['&eacute;', '&amp;', '&#233;'],
			],
			'Portuguese accents and cedilla' => [
				'testName' => 'Portuguese accents',
				'signedByText' => 'Assinado digitalmente por João da Silva',
				'expectedSubstrings' => ['João', 'Silva'],
				'forbiddenSubstrings' => ['&atilde;', '&amp;', '&#227;'],
			],
			'Spanish ñ and accents' => [
				'testName' => 'Spanish characters',
				'signedByText' => 'Firmado digitalmente por José Muñoz',
				'expectedSubstrings' => ['José', 'Muñoz'],
				'forbiddenSubstrings' => ['&ntilde;', '&eacute;', '&amp;'],
			],
			'German umlauts' => [
				'testName' => 'German umlauts',
				'signedByText' => 'Digital signiert von Müller & Söhne',
				'expectedSubstrings' => ['Müller', 'Söhne'],
				'forbiddenSubstrings' => ['&uuml;', '&ouml;', '&amp;'],
			],
			'Multiple special characters' => [
				'testName' => 'Multiple accents',
				'signedByText' => 'Signé par Renée & José',
				'expectedSubstrings' => ['Signé', 'Renée', 'José'],
				'forbiddenSubstrings' => ['&eacute;', '&amp;', '&#'],
			],
			'Greek characters' => [
				'testName' => 'Greek characters',
				'signedByText' => 'Υπογραφή από Αθήνα',
				'expectedSubstrings' => ['Υπογραφή', 'Αθήνα'],
				'forbiddenSubstrings' => ['&amp;', '&#'],
			],
			'Cyrillic characters' => [
				'testName' => 'Cyrillic characters',
				'signedByText' => 'Подписано Москва',
				'expectedSubstrings' => ['Подписано', 'Москва'],
				'forbiddenSubstrings' => ['&amp;', '&#'],
			],
			'Arabic characters (RTL)' => [
				'testName' => 'Arabic (RTL)',
				'signedByText' => 'توقيع رقمي من القاهرة',
				'expectedSubstrings' => [
					['عيقوت', 'ﻊﻴﻗﻮﺗ'],
					['ةرهاقلا', 'ةﺮﻫﺎﻘﻟا'],
				],
				'forbiddenSubstrings' => ['&amp;', '&#'],
			],
			'Hebrew characters (RTL)' => [
				'testName' => 'Hebrew (RTL)',
				'signedByText' => 'חתום דיגיטלית מירושלים',
				'expectedSubstrings' => ['םותח', 'םילשורימ'], // RTL text appears reversed in extracted PDF
				'forbiddenSubstrings' => ['&amp;', '&#'],
			],
			'Chinese characters' => [
				'testName' => 'Chinese (CJK)',
				'signedByText' => '数字签名 北京',
				'expectedSubstrings' => ['数字签名', '北京'],
				'forbiddenSubstrings' => ['&amp;', '&#'],
			],
			'Japanese characters' => [
				'testName' => 'Japanese (CJK)',
				'signedByText' => 'デジタル署名 東京',
				'expectedSubstrings' => ['デジタル署名', '東京'],
				'forbiddenSubstrings' => ['&amp;', '&#'],
			],
			'Korean characters' => [
				'testName' => 'Korean (CJK)',
				'signedByText' => '디지털 서명 서울',
				'expectedSubstrings' => ['디지털', '서울'],
				'forbiddenSubstrings' => ['&amp;', '&#'],
			],
			'Emoji characters' => [
				'testName' => 'Emoji',
				'signedByText' => 'Signed ✍️ by LibreSign 🔒',
				'expectedSubstrings' => ['Signed', 'LibreSign'],
				'forbiddenSubstrings' => ['&amp;', '&#'],
			],
			'Mixed emoji and accents' => [
				'testName' => 'Emoji with accents',
				'signedByText' => 'Signé 📝 par José 👤',
				'expectedSubstrings' => ['Signé', 'José'],
				'forbiddenSubstrings' => ['&eacute;', '&amp;', '&#'],
			],
		];
	}
}
