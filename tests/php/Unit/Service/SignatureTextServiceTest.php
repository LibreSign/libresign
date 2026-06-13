<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service;

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Imagick;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Font\BundledFontLocator;
use OCA\Libresign\Service\Font\FontReferenceResolver;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Tests\Unit\Service\Font\InMemoryLogger;
use OCP\IAppConfig;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

final class SignatureTextServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignatureTextService $service;
	private IAppConfig $appConfig;
	private IL10N $l10n;
	private IDateTimeZone $dateTimeZone;
	private IRequest $request;
	private IUserSession $userSession;
	private IURLGenerator&MockObject $urlGenerator;
	private InMemoryLogger $logger;
	private BundledFontLocator $bundledFontLocator;

	#[\Override]
	public function setUp(): void {
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->dateTimeZone = \OCP\Server::get(IDateTimeZone::class);
		$this->request = $this->createStub(IRequest::class);
		$this->userSession = $this->createStub(IUserSession::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->urlGenerator
			->method('linkToRouteAbsolute')
			->willReturnCallback(fn (string $route, array $params): string => 'https://example.test/' . $route . '/' . ($params['uuid'] ?? ''));
		$this->logger = new InMemoryLogger();
		$this->bundledFontLocator = new BundledFontLocator();
	}

	private function getClass(): SignatureTextService {
		$this->service = new SignatureTextService(
			$this->appConfig,
			$this->l10n,
			$this->dateTimeZone,
			$this->request,
			$this->userSession,
			$this->urlGenerator,
			$this->logger,
			new FontReferenceResolver($this->bundledFontLocator, $this->logger),
			new \OCA\Libresign\Service\SignatureTextLineBreaker(),
		);
		return $this->service;
	}

	public function testCollectingMetadata(): void {
		$this->appConfig->setValueBool(Application::APP_ID, 'collect_metadata', true);

		$actual = $this->getClass()->getAvailableVariables();
		$this->assertArrayHasKey('{{SignerIP}}', $actual);
		$this->assertArrayHasKey('{{SignerUserAgent}}', $actual);
		$this->assertArrayHasKey('{{qrcode}}', $actual);
		$this->assertArrayHasKey('{{ValidationURL}}', $actual);

		$template = $this->getClass()->getDefaultTemplate();
		$this->assertStringContainsString('IP', $template);
	}

	public function testNotCollectingMetadata(): void {
		$this->appConfig->setValueBool(Application::APP_ID, 'collect_metadata', false);

		$actual = $this->getClass()->getAvailableVariables();
		$this->assertArrayNotHasKey('{{SignerIP}}', $actual);
		$this->assertArrayNotHasKey('{{SignerUserAgent}}', $actual);
		$this->assertArrayHasKey('{{qrcode}}', $actual);
		$this->assertArrayHasKey('{{ValidationURL}}', $actual);

		$template = $this->getClass()->getDefaultTemplate();
		$this->assertStringNotContainsString('IP', $template);
	}

	#[DataProvider('providerSave')]
	public function testSave($template, $context, $parsed): void {
		$fromSave = $this->getClass()->save($template);
		$fromParse = $this->getClass()->parse($fromSave['template'], $context);
		$this->assertEquals($parsed, $fromParse['parsed']);
	}

	public static function providerSave(): array {
		return [
			'empty' => ['', [], ''],
			'without vars' => ['Just a static text.', [], 'Just a static text.',],
			'plain text with vars' => [
				'{{SignerCommonName}} signed the document on {{ServerSignatureDate}}',
				[
					'SignerCommonName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				'John Doe signed the document on 2025-03-31T23:53:47+00:00',
			],
			'plain text with vars and line break' => [
				"{{SignerCommonName}}\nsigned the document on {{ServerSignatureDate}}",
				[
					'SignerCommonName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				"John Doe\nsigned the document on 2025-03-31T23:53:47+00:00",
			],
			'b tag' => [
				"<b>{{SignerCommonName}}</b>\nsigned the document on {{ServerSignatureDate}}",
				[
					'SignerCommonName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				"John Doe\nsigned the document on 2025-03-31T23:53:47+00:00",
			],
			'p tag' => [
				'<p>{{SignerCommonName}}</p><p>signed the document on {{ServerSignatureDate}}</p>',
				[
					'SignerCommonName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				"John Doe\nsigned the document on 2025-03-31T23:53:47+00:00",
			],
			'br and p' => [
				'<p>{{SignerCommonName}}</p><br><p>signed the document on {{ServerSignatureDate}}</p>',
				[
					'SignerCommonName' => 'John Doe',
					'ServerSignatureDate' => '2025-03-31T23:53:47+00:00',
				],
				"John Doe\n\nsigned the document on 2025-03-31T23:53:47+00:00",
			],
		];
	}

	public function testParseShouldGenerateQrcodeAsBase64FromValidationUrl(): void {
		$actual = $this->getClass()->parse('{{qrcode}}', [
			'DocumentUUID' => 'abc-123',
			'ValidationURL' => 'https://validator.example/abc-123',
		]);

		$this->assertNotEmpty($actual['parsed']);
		$this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $actual['parsed']);
		$this->assertNotEquals('https://validator.example/abc-123', $actual['parsed']);

		$decoded = base64_decode($actual['parsed'], true);
		$this->assertNotFalse($decoded);
		$this->assertStringStartsWith("\x89PNG\r\n\x1A\n", $decoded);
	}

	public function testParseBuildsValidationUrlAndQrcodeFromDocumentUuid(): void {
		$this->appConfig->setValueString(Application::APP_ID, 'validation_site', 'https://validator.example/base');

		$actual = $this->getClass()->parse('{{ValidationURL}}|{{qrcode}}', [
			'DocumentUUID' => 'abc-123',
		]);

		[$validationUrl, $qrcode] = explode('|', $actual['parsed'], 2);

		$this->assertSame('https://validator.example/base/abc-123', $validationUrl);
		$this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $qrcode);
		$decoded = base64_decode($qrcode, true);
		$this->assertNotFalse($decoded);
		$this->assertStringStartsWith("\x89PNG\r\n\x1A\n", $decoded);
	}

	#[DataProvider('providerTemplateFontSizeScenarios')]
	public function testTemplateFontSizesDependOnMetadataCollection(
		bool $collectMetadata,
		float $configuredTemplateFontSize,
		float $expectedTemplateFontSize,
		float $expectedDefaultTemplateFontSize,
	): void {
		$this->appConfig->setValueBool(Application::APP_ID, 'collect_metadata', $collectMetadata);
		$this->appConfig->setValueFloat(Application::APP_ID, 'template_font_size', $configuredTemplateFontSize);

		$class = $this->getClass();

		$this->assertSame($expectedTemplateFontSize, $class->getTemplateFontSize());
		$this->assertSame($expectedDefaultTemplateFontSize, $class->getDefaultTemplateFontSize());
	}

	public static function providerTemplateFontSizeScenarios(): array {
		return [
			'metadata disabled keeps configured size and standard default size' => [false, 12.5, 12.5, 10.0],
			'metadata enabled keeps configured size and reduces default size' => [true, 8.75, 8.75, 9.8],
		];
	}

	#[DataProvider('providerSignatureWidthScenarios')]
	public function testGetSignatureWidthDependsOnRenderModeAndTemplate(
		string $renderMode,
		?string $template,
		float $expectedSignatureWidth,
	): void {
		$this->appConfig->setValueFloat(Application::APP_ID, 'signature_width', 400.0);
		$this->appConfig->setValueString(Application::APP_ID, 'signature_render_mode', $renderMode);
		if ($template === null) {
			$this->appConfig->deleteKey(Application::APP_ID, 'signature_text_template');
		} else {
			$this->appConfig->setValueString(Application::APP_ID, 'signature_text_template', $template);
		}

		$class = $this->getClass();

		$this->assertSame($expectedSignatureWidth, $class->getSignatureWidth());
		$this->assertSame($class->getFullSignatureHeight(), $class->getSignatureHeight());
	}

	public static function providerSignatureWidthScenarios(): array {
		return [
			'default render mode halves width when template is enabled' => [
				SignerElementsService::RENDER_MODE_DEFAULT,
				'Signed by {{SignerCommonName}}',
				200.0,
			],
			'graphic only keeps full width' => [
				SignerElementsService::RENDER_MODE_GRAPHIC_ONLY,
				'Signed by {{SignerCommonName}}',
				400.0,
			],
			'empty configured template keeps full width' => [
				SignerElementsService::RENDER_MODE_DEFAULT,
				'',
				400.0,
			],
			'not configured uses default template and halves width' => [
				SignerElementsService::RENDER_MODE_DEFAULT,
				null,
				200.0,
			],
		];
	}

	#[DataProvider('providerSignerNameImage')]
	public function testSignerNameImageVariants(
		string $text,
		int $width,
		int $height,
		string $align,
		float $scale,
	): void {
		$class = $this->getClass();
		$blob = $class->signerNameImage(
			text: $text,
			width: $width,
			height: $height,
			align: $align,
			scale: $scale
		);

		$image = new Imagick();
		$image->readImageBlob($blob);

		$expectedWidth = (int)($width * $scale);
		$expectedHeight = (int)($height * $scale);

		$this->assertEquals($expectedWidth, $image->getImageWidth());
		$this->assertEquals($expectedHeight, $image->getImageHeight());
	}

	public static function providerSignerNameImage(): array {
		return [
			'empty text keeps canvas dimensions' => ['', 350, 100, 'center', 5],
			'center 350x100 scale 5' => ['LibreSign', 350, 100, 'center', 5],
			'left 350x100 scale 4' => ['Secure signature', 350, 100, 'left', 4],
			'right 350x100 scale 3' => ['Verified by LibreCode', 350, 100, 'right', 3],

			'center 175x100 scale 2' => ['Fast & Easy Signing', 175, 100, 'center', 2],
			'left 175x100 scale 1.5' => ['LibreSign Service', 175, 100, 'left', 1.5],
			'right 175x100 scale 1' => ['Electronic Docs', 175, 100, 'right', 1],

			'center 175x50 scale 2.5' => ['Secure ✔️', 175, 50, 'center', 2.5],
			'left 175x50 scale 3' => ['Sign now', 175, 50, 'left',3],
			'right 175x50 scale 4' => ['Signed 🔐', 175, 50, 'right', 4],

			// Portuguese text with accents
			'center 175x101 portuguese' => ['Imagem da assinatura aqui', 175, 101, 'center', 5],
			'right 175x101 portuguese' => ['Imagem da assinatura aqui', 175, 101, 'right', 5],
			'left 350x100 portuguese long' => ['Assinado com LibreSign administrador', 350, 100, 'left', 5],
		];
	}

	public function testHasFont(): void {
		$this->assertFileExists($this->bundledFontLocator->requireFontFile('DejaVuSerifCondensed.ttf'));
	}

	#[DataProvider('providerInvalidSignatureDimensions')]
	public function testSaveShouldRejectInvalidSignatureDimensions(float $signatureWidth, float $signatureHeight): void {
		$this->expectException(LibresignException::class);

		$this->getClass()->save(
			template: 'valid',
			signatureWidth: $signatureWidth,
			signatureHeight: $signatureHeight,
		);
	}

	public static function providerInvalidSignatureDimensions(): array {
		return [
			'zero width' => [0.0, 100.0],
			'fractional width below minimum' => [0.9999, 100.0],
			'subnormal width' => [1.0E-320, 100.0],
			'scientific width below minimum' => [1.0E-6, 100.0],
			'negative width' => [-1.0, 100.0],
			'very small negative width' => [-0.0001, 100.0],
			'zero height' => [350.0, 0.0],
			'fractional height below minimum' => [350.0, 0.9999],
			'subnormal height' => [350.0, 1.0E-320],
			'scientific height below minimum' => [350.0, 1.0E-6],
			'negative height' => [350.0, -1.0],
			'very small negative height' => [350.0, -0.0001],
			'both dimensions zero' => [0.0, 0.0],
			'both dimensions negative' => [-1.0, -1.0],
			'both dimensions fractional below minimum' => [0.5, 0.5],
		];
	}

	#[DataProvider('providerInvalidStoredDimensions')]
	public function testInvalidStoredDimensionsFallbackToDefaultsAndLogWarnings(
		float $configuredWidth,
		float $configuredHeight,
		array $expectedInvalidKeys,
	): void {
		$this->appConfig->setValueFloat(Application::APP_ID, 'signature_width', $configuredWidth);
		$this->appConfig->setValueFloat(Application::APP_ID, 'signature_height', $configuredHeight);

		$class = $this->getClass();

		$this->assertEquals(SignatureTextService::DEFAULT_SIGNATURE_WIDTH, $class->getFullSignatureWidth());
		$this->assertEquals(SignatureTextService::DEFAULT_SIGNATURE_HEIGHT, $class->getFullSignatureHeight());

		$this->assertSame(
			in_array('signature_width', $expectedInvalidKeys, true) ? (float)SignatureTextService::DEFAULT_SIGNATURE_WIDTH : $configuredWidth,
			$this->appConfig->getValueFloat(Application::APP_ID, 'signature_width', -1)
		);
		$this->assertSame(
			in_array('signature_height', $expectedInvalidKeys, true) ? (float)SignatureTextService::DEFAULT_SIGNATURE_HEIGHT : $configuredHeight,
			$this->appConfig->getValueFloat(Application::APP_ID, 'signature_height', -1)
		);

		$warnings = $this->logger->warnings();
		$this->assertCount(count($expectedInvalidKeys), $warnings);
		$this->assertSame($expectedInvalidKeys, array_map(
			static fn (array $warning): string => $warning['context']['key'],
			$warnings,
		));
	}

	public static function providerInvalidStoredDimensions(): array {
		return [
			'invalid width only' => [0.0, 100.0, ['signature_width']],
			'invalid height only' => [350.0, -1.0, ['signature_height']],
			'both invalid' => [0.0, -1.0, ['signature_width', 'signature_height']],
		];
	}

	#[DataProvider('providerEnabledStates')]
	public function testIsEnabledDependsOnConfiguredTemplate(?string $configuredTemplate, bool $expectedEnabled): void {
		if ($configuredTemplate === null) {
			$this->appConfig->deleteKey(Application::APP_ID, 'signature_text_template');
		} else {
			$this->appConfig->setValueString(Application::APP_ID, 'signature_text_template', $configuredTemplate);
		}

		$this->assertSame($expectedEnabled, $this->getClass()->isEnabled());
	}

	public static function providerEnabledStates(): array {
		return [
			'not configured uses default template' => [null, true],
			'empty configured template disables signature text' => ['', false],
			'custom configured template enables signature text' => ['Signed by {{SignerCommonName}}', true],
		];
	}
}
