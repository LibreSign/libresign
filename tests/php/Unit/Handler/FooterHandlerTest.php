<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Handler;

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Handler\TemplateVariables;
use OCA\Libresign\Service\File\Pdf\PdfMetadataExtractor;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class FooterHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAppConfig $appConfig;
	private PdfMetadataExtractor&MockObject $pdfMetadataExtractor;
	private IURLGenerator&MockObject $urlGenerator;
	private PolicyService&MockObject $policyService;
	private IL10N $l10n;
	private IFactory $l10nFactory;
	private ITempManager $tempManager;
	private FooterHandler $footerHandler;
	public function setUp(): void {
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->pdfMetadataExtractor = $this->createMock(PdfMetadataExtractor::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->policyService
			->method('resolve')
			->willReturnCallback(function (string $policyKey): ResolvedPolicy {
				$value = match ($policyKey) {
					FooterPolicy::KEY => $this->appConfig->getValueString(Application::APP_ID, 'add_footer', '1'),
					default => null,
				};

				return (new ResolvedPolicy())
					->setPolicyKey($policyKey)
					->setEffectiveValue($value)
					->setSourceScope('system')
					->setVisible(true)
					->setEditableByCurrentActor(true)
					->setAllowedValues([true, false]);
			});
		$this->tempManager = \OCP\Server::get(ITempManager::class);
		$this->l10nFactory = \OCP\Server::get(IFactory::class);
	}

	private function getClass(): FooterHandler {
		$templateVars = new TemplateVariables($this->l10n);
		$this->footerHandler = new FooterHandler(
			$this->appConfig,
			$this->pdfMetadataExtractor,
			$this->urlGenerator,
			$this->l10n,
			$this->l10nFactory,
			$this->tempManager,
			$this->policyService,
			$templateVars,
		);
		return $this->footerHandler;
	}

	public function testGetFooterWithoutValidationSite(): void {
		$this->appConfig->setValueString(
			Application::APP_ID,
			'add_footer',
			FooterPolicyValue::encode([
				'enabled' => false,
				'writeQrcodeOnFooter' => true,
				'validationSite' => '',
				'customizeFooterTemplate' => false,
			]),
		);
		$dimensions = [['w' => 595, 'h' => 842]];
		$this->l10n = $this->l10nFactory->get(Application::APP_ID);
		$actual = $this->getClass()
			->setTemplateVar('uuid', 'test-uuid')
			->getFooter($dimensions);
		$this->assertEmpty($actual);
	}

	#[DataProvider('dataGetFooterWithSuccess')]
	public function testGetFooterWithSuccess(string $language, array $settings, array $expected): void {
		foreach ($settings as $key => $value) {
			if ($key === 'add_footer') {
				$policyValidationSite = isset($settings['validation_site']) && is_string($settings['validation_site'])
					? $settings['validation_site']
					: '';
				$this->appConfig->setValueString(
					Application::APP_ID,
					'add_footer',
					FooterPolicyValue::encode([
						'enabled' => (bool)$value,
						'writeQrcodeOnFooter' => true,
						'validationSite' => $policyValidationSite,
						'customizeFooterTemplate' => false,
					]),
				);
				continue;
			}

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
			if (!empty($expected['qrcode'])) {
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

	private function extractPdfContent(string $content, array $keys, string $direction): array {
		$this->assertNotEmpty($content, 'Empty PDF file');
		$this->assertNotEmpty($keys, 'Is necessary to send a not empty array of fields to search at PDF file');
		$parser = new \Smalot\PdfParser\Parser();
		$pdf = $parser->parseContent($content);
		$text = $pdf->getText();
		$this->assertNotEmpty($text, 'PDF without text');
		$content = explode("\n", $text);
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
		$this->appConfig->setValueString(
			Application::APP_ID,
			'add_footer',
			FooterPolicyValue::encode([
				'enabled' => true,
				'writeQrcodeOnFooter' => true,
				'validationSite' => '',
				'customizeFooterTemplate' => false,
			]),
		);
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

	public function testCustomValidationSiteNotOverwritten(): void {
		$this->appConfig->setValueString(
			Application::APP_ID,
			'add_footer',
			FooterPolicyValue::encode([
				'enabled' => true,
				'writeQrcodeOnFooter' => true,
				'validationSite' => '',
				'customizeFooterTemplate' => false,
			]),
		);
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

	public function testGetTemplateReturnsCustomTemplate(): void {
		$customTemplate = '<div>Custom footer template {{ uuid }}</div>';
		$this->appConfig->setValueString(Application::APP_ID, 'footer_template', $customTemplate);
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$template = $this->getClass()->getTemplate();

		$this->assertSame($customTemplate, $template);
	}

	public function testGetTemplateReturnsDefaultWhenNotSet(): void {
		$this->appConfig->deleteKey(Application::APP_ID, 'footer_template');
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$template = $this->getClass()->getTemplate();

		$defaultTemplate = file_get_contents(__DIR__ . '/../../../../lib/Handler/Templates/footer.twig');
		$this->assertNotEmpty($template);
		$this->assertSame($defaultTemplate, $template);
	}

	public function testGetTemplateReturnsDefaultWhenEmpty(): void {
		$this->appConfig->setValueString(Application::APP_ID, 'footer_template', '');
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$template = $this->getClass()->getTemplate();

		$this->assertNotEmpty($template);
		$defaultTemplate = file_get_contents(__DIR__ . '/../../../../lib/Handler/Templates/footer.twig');
		$this->assertSame($defaultTemplate, $template);
	}

	public function testGetTemplateVariablesMetadata(): void {
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');
		$metadata = $this->getClass()->getTemplateVariablesMetadata();

		$this->assertIsArray($metadata);
		$this->assertCount(9, $metadata);
		$this->assertArrayHasKey('direction', $metadata);
		$this->assertArrayHasKey('uuid', $metadata);
		$this->assertArrayHasKey('signedBy', $metadata);
		$this->assertSame('string', $metadata['direction']['type']);
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
		$this->appConfig->setValueString(
			Application::APP_ID,
			'add_footer',
			FooterPolicyValue::encode([
				'enabled' => true,
				'writeQrcodeOnFooter' => true,
				'validationSite' => '',
				'customizeFooterTemplate' => false,
			]),
		);
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
			$this->assertStringContainsString($expected, $text, "Expected to find '{$expected}' for test: {$testName}");
		}

		foreach ($forbiddenSubstrings as $forbidden) {
			$this->assertStringNotContainsString($forbidden, $text, "Should not find '{$forbidden}' for test: {$testName}");
		}
	}

	public function testPolicyCanDisableQrCodeRendering(): void {
		$this->appConfig->setValueString(
			Application::APP_ID,
			'add_footer',
			FooterPolicyValue::encode([
				'enabled' => true,
				'writeQrcodeOnFooter' => false,
				'validationSite' => 'https://validation.example',
				'customizeFooterTemplate' => false,
			]),
		);
		$this->appConfig->setValueString(
			Application::APP_ID,
			'footer_template',
			'<div>qrcode:{{ qrcode }} validateIn:{{ validationSite }}</div>',
		);

		$dimensions = [['w' => 595, 'h' => 100]];
		$this->l10n = $this->l10nFactory->get(Application::APP_ID, 'en');

		$pdf = $this->getClass()
			->setTemplateVar('uuid', 'unit-test-uuid')
			->getFooter($dimensions);

		$this->assertNotEmpty($pdf);
		$parser = new \Smalot\PdfParser\Parser();
		$pdfParsed = $parser->parseContent($pdf);
		$text = $pdfParsed->getText();
		$this->assertStringContainsString('validateIn:https://validation.example/unit-test-uuid', str_replace(' ', '', $text));
		$this->assertStringNotContainsString('iVBOR', $text);
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
				'expectedSubstrings' => ['عيقوت', 'ةرهاقلا'], // RTL text appears reversed in extracted PDF
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
