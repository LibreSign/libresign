<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Handler\SignEngine;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\SignEngine\PhpNativeHandler;
use OCA\Libresign\Service\DocMdp\ConfigService as DocMdpConfigService;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\Files\File;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use SignerPHP\Application\DTO\CertificationLevel;
use SignerPHP\Application\DTO\SignatureAppearanceDto;
use SignerPHP\Application\DTO\TimestampOptionsDto;

final class PhpNativeHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAppConfig $appConfig;
	private DocMdpConfigService&MockObject $docMdpConfigService;
	private SignatureTextService&MockObject $signatureTextService;
	private SignatureBackgroundService&MockObject $signatureBackgroundService;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;

	public function setUp(): void {
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->docMdpConfigService = $this->createMock(DocMdpConfigService::class);
		$this->signatureTextService = $this->createMock(SignatureTextService::class);
		$this->signatureBackgroundService = $this->createMock(SignatureBackgroundService::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
	}

	public function testBuildAppearanceSkipsBackgroundWhenDisabled(): void {
		$handler = $this->getHandler();

		$this->signatureBackgroundService
			->expects($this->once())
			->method('isEnabled')
			->willReturn(false);
		$this->signatureBackgroundService
			->expects($this->never())
			->method('getImagePath');

		$appearance = $this->callPrivateMethod(
			$handler,
			'buildAppearanceForElement',
			10.0,
			20.0,
			110.0,
			70.0,
			800.0,
			0,
			100,
			50,
		);

		$this->assertInstanceOf(SignatureAppearanceDto::class, $appearance);
		$this->assertNull($appearance->backgroundImagePath);
	}

	public function testBuildAppearanceConvertsPdfCoordinatesToScreenCoordinates(): void {
		$handler = $this->getHandler();

		$this->signatureBackgroundService->method('isEnabled')->willReturn(false);

		$appearance = $this->callPrivateMethod(
			$handler,
			'buildAppearanceForElement',
			10.0,
			20.0,
			110.0,
			70.0,
			800.0,
			1,
			100,
			50,
		);

		$this->assertSame([10.0, 730.0, 110.0, 780.0], $appearance->rect);
		$this->assertSame(1, $appearance->page);
		$this->assertNotNull($appearance->xObject);
		$this->assertStringContainsString('Signed by', $appearance->xObject->stream);
	}

	public function testResolvePageHeightThrowsWhenDimensionsAreMissing(): void {
		$handler = $this->getHandler();

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing or invalid PageDimensions for page index 0.');

		$this->callPrivateMethod($handler, 'resolvePageHeight', [], 0);
	}

	#[DataProvider('providerWrapTextForPdf')]
	public function testWrapTextForPdf(string $line, float $availableWidth, float $fontSize, array $expected): void {
		$handler = $this->getHandler();
		$actual = $this->callPrivateMethod($handler, 'wrapTextForPdf', $line, $availableWidth, $fontSize);
		$this->assertSame($expected, $actual);
	}

	public static function providerWrapTextForPdf(): array {
		return [
			'empty string returns single empty element' => [
				'', 100.0, 10.0, [''],
			],
			'short text that fits in one line' => [
				'hello', 100.0, 10.0, ['hello'],
			],
			'multiple words wrapped at word boundaries' => [
				// fontSize=10 → estimatedCharWidth=5.2; availableWidth=20 → maxChars=3
				// 'ab cd ef' → ['ab', 'cd', 'ef']
				'ab cd ef', 20.0, 10.0, ['ab', 'cd', 'ef'],
			],
			'single long word is hard-split at maxChars' => [
				// maxChars=floor(15/5.2)=2: 'abcdefgh' → ['ab','cd','ef','gh']
				'abcdefgh', 15.0, 10.0, ['ab', 'cd', 'ef', 'gh'],
			],
		];
	}

	#[DataProvider('providerEscapePdfText')]
	public function testEscapePdfText(string $input, string $expected): void {
		$handler = $this->getHandler();
		$actual = $this->callPrivateMethod($handler, 'escapePdfText', $input);
		$this->assertSame($expected, $actual);
	}

	public static function providerEscapePdfText(): array {
		return [
			'plain text is unchanged' => ['hello world', 'hello world'],
			'backslash is doubled' => ['back\\slash', 'back\\\\slash'],
			'opening parenthesis is escaped' => ['open(paren', 'open\\(paren'],
			'closing parenthesis is escaped' => ['close)paren', 'close\\)paren'],
			'multiple special chars in one string' => ['a\\b(c)d', 'a\\\\b\\(c\\)d'],
		];
	}

	#[DataProvider('providerHasExistingSignatures')]
	public function testHasExistingSignatures(string $pdfContent, bool $expected): void {
		$handler = $this->getHandler();
		$actual = $this->callPrivateMethod($handler, 'hasExistingSignatures', $pdfContent);
		$this->assertSame($expected, $actual);
	}

	public static function providerHasExistingSignatures(): array {
		return [
			'ByteRange marker signals existing signature' => ['/ByteRange [0 0 0 0]', true],
			'/Type /Sig signals existing signature' => ['/Type /Sig ', true],
			'/DocMDP signals existing signature' => ['/DocMDP ', true],
			'/Perms signals existing signature' => ['/Perms ', true],
			'plain PDF content has no signature' => ['%PDF-1.4 startxref 0 %%EOF', false],
		];
	}

	#[DataProvider('providerBuildTimestampOptions')]
	public function testBuildTimestampOptions(
		string $tsaUrl,
		string $authType,
		string $username,
		string $password,
		bool $expectNull,
		?string $expectedUrl,
		?string $expectedUsername,
		?string $expectedPassword,
	): void {
		$this->appConfig->setValueString('libresign', 'tsa_url', $tsaUrl);
		$this->appConfig->setValueString('libresign', 'tsa_auth_type', $authType);
		$this->appConfig->setValueString('libresign', 'tsa_username', $username);
		$this->appConfig->setValueString('libresign', 'tsa_password', $password);

		$handler = $this->getHandler();
		$result = $this->callPrivateMethod($handler, 'buildTimestampOptions');

		if ($expectNull) {
			$this->assertNull($result);
			return;
		}

		$this->assertInstanceOf(TimestampOptionsDto::class, $result);
		$this->assertSame($expectedUrl, $result->tsaUrl);
		$this->assertSame($expectedUsername, $result->username);
		$this->assertSame($expectedPassword, $result->password);
	}

	public static function providerBuildTimestampOptions(): array {
		return [
			'no TSA URL returns null' => [
				'', 'none', '', '', true, null, null, null,
			],
			'TSA URL with no auth returns DTO without credentials' => [
				'http://tsa.example.com', 'none', 'ignored', 'ignored',
				false, 'http://tsa.example.com', null, null,
			],
			'TSA URL with basic auth returns DTO with credentials' => [
				'http://tsa.example.com', 'basic', 'alice', 's3cr3t',
				false, 'http://tsa.example.com', 'alice', 's3cr3t',
			],
			'basic auth with empty username and password returns null credentials in DTO' => [
				'http://tsa.example.com', 'basic', '', '',
				false, 'http://tsa.example.com', null, null,
			],
		];
	}

	#[DataProvider('providerResolveCertificationLevel')]
	public function testResolveCertificationLevel(
		bool $docMdpEnabled,
		bool $noVisibleElements,
		string $pdfContent,
		bool $expectNull,
	): void {
		$this->docMdpConfigService->method('isEnabled')->willReturn($docMdpEnabled);
		if ($docMdpEnabled) {
			$this->docMdpConfigService->method('getLevel')
				->willReturn(DocMdpLevel::CERTIFIED_FORM_FILLING);
		}

		$handler = $this->getHandler();

		if (!$noVisibleElements) {
			$inputFile = $this->createMock(File::class);
			$inputFile->method('getContent')->willReturn($pdfContent);
			$handler->setInputFile($inputFile);
		}

		$result = $this->callPrivateMethod($handler, 'resolveCertificationLevel', $noVisibleElements);

		if ($expectNull) {
			$this->assertNull($result);
		} else {
			$this->assertInstanceOf(CertificationLevel::class, $result);
		}
	}

	public static function providerResolveCertificationLevel(): array {
		return [
			'DocMDP disabled always returns null' => [
				false, true, '', true,
			],
			'DocMDP enabled with no visible elements certifies' => [
				true, true, '', false,
			],
			'DocMDP enabled, visible elements, clean PDF certifies first signature' => [
				true, false, '%PDF-1.4 startxref 0 %%EOF', false,
			],
			'DocMDP enabled, visible elements, PDF already signed skips certification' => [
				true, false, '/ByteRange [0 0 0 0]', true,
			],
		];
	}

	public function testBuildAppearanceForElementSetsSignatureImageInGraphicAndDescriptionMode(): void {
		$imagePath = realpath(__DIR__ . '/../../../../../img/app-dark.png');
		$this->assertNotFalse($imagePath, 'Test image must exist');

		$handler = $this->getHandlerWithMode(SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION);
		$this->signatureBackgroundService->method('isEnabled')->willReturn(false);

		$appearance = $this->callPrivateMethod(
			$handler,
			'buildAppearanceForElement',
			10.0, 20.0, 110.0, 70.0, 800.0, 0, 100, 50,
			$imagePath,
		);

		$this->assertInstanceOf(SignatureAppearanceDto::class, $appearance);
		$this->assertSame($imagePath, $appearance->signatureImagePath);
		// Frame positions the image on the left half: [0, 0, width/2, height]
		$this->assertSame([0.0, 0.0, 50.0, 50.0], $appearance->signatureImageFrame);
	}

	public function testBuildAppearanceForElementDoesNotSetSignatureImageWhenNoFile(): void {
		$handler = $this->getHandlerWithMode(SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION);
		$this->signatureBackgroundService->method('isEnabled')->willReturn(false);

		$appearance = $this->callPrivateMethod(
			$handler,
			'buildAppearanceForElement',
			10.0, 20.0, 110.0, 70.0, 800.0, 0, 100, 50,
			'', // empty path
		);

		$this->assertNull($appearance->signatureImagePath);
		$this->assertNull($appearance->signatureImageFrame);
	}

	public function testBuildXObjectDescriptionOnlyPositionsTextAtLeftPadding(): void {
		// leftPadding = max(2.0, 10.0 * 0.15) = 2.0; currentY = 50 - 10 - 2 = 38.0
		$handler = $this->getHandlerWithMode(SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY);
		$xObject = $this->callPrivateMethod(
			$handler, 'buildXObject', 100, 50, SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
		);

		// Description text must begin at X = leftPadding = 2.00 (full width, not offset to right half)
		$this->assertStringContainsString('2.00 38.00 Td', $xObject->stream);
		$this->assertStringNotContainsString('52.00 ', $xObject->stream);
	}

	public function testBuildXObjectGraphicAndDescriptionPositionsTextAtRightHalf(): void {
		// textStartX = width/2 + leftPadding = 50 + 2 = 52.0; currentY = 50 - 10 - 2 = 38.0
		$handler = $this->getHandlerWithMode(SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION);
		$xObject = $this->callPrivateMethod(
			$handler, 'buildXObject', 100, 50, SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION,
		);

		// Text must start at the right half (X = 52.00), not at leftPadding alone
		$this->assertStringContainsString('52.00 38.00 Td', $xObject->stream);
		// Ensure text is NOT starting at leftPadding only (would be \n2.00 ... in DESCRIPTION_ONLY)
		$this->assertStringNotContainsString("\n2.00 38.00 Td", $xObject->stream);
	}

	public function testBuildXObjectSignameAndDescriptionIncludesNameAndDescriptionBlocks(): void {
		$handler = $this->getHandlerWithMode(SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION);
		$handler->setSignatureParams(['SignerCommonName' => 'Test User']);
		$xObject = $this->callPrivateMethod(
			$handler, 'buildXObject', 200, 80, SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION,
		);

		// Name block uses the larger signature font (20.0)
		$this->assertStringContainsString('/F1 20.00 Tf', $xObject->stream);
		$this->assertStringContainsString('(Test User) Tj', $xObject->stream);
		// Description block uses the description font (10.0)
		$this->assertStringContainsString('/F1 10.00 Tf', $xObject->stream);
		// Description text positioned on the right half (X = 200/2 + 2 = 102.0)
		$this->assertStringContainsString('102.00 ', $xObject->stream);
	}

	/**
	 * Regression: GRAPHIC_ONLY mode must not render any text in the n2 xObject layer.
	 * Before the fix, the method fell through to the description block and wrote text
	 * into the stamp.
	 */
	public function testBuildXObjectGraphicOnlyReturnsEmptyStream(): void {
		$handler = $this->getHandlerWithMode(SignerElementsService::RENDER_MODE_GRAPHIC_ONLY);
		$xObject = $this->callPrivateMethod(
			$handler, 'buildXObject', 100, 50, SignerElementsService::RENDER_MODE_GRAPHIC_ONLY,
		);

		$this->assertSame('', $xObject->stream);
		$this->assertSame([], $xObject->resources);
	}

	/**
	 * Regression: GRAPHIC_ONLY mode must assign the user's drawn image to the full bbox
	 * (signatureImageFrame = null).  Before the fix only GRAPHIC_AND_DESCRIPTION set
	 * signatureImagePath, leaving GRAPHIC_ONLY with no image (blank stamp).
	 */
	public function testBuildAppearanceForElementSetsSignatureImageInGraphicOnlyMode(): void {
		$imagePath = realpath(__DIR__ . '/../../../../../img/app-dark.png');
		$this->assertNotFalse($imagePath, 'Test image must exist');

		$handler = $this->getHandlerWithMode(SignerElementsService::RENDER_MODE_GRAPHIC_ONLY);
		$this->signatureBackgroundService->method('isEnabled')->willReturn(false);

		$appearance = $this->callPrivateMethod(
			$handler,
			'buildAppearanceForElement',
			10.0, 20.0, 110.0, 70.0, 800.0, 0, 100, 50,
			$imagePath,
		);

		$this->assertInstanceOf(SignatureAppearanceDto::class, $appearance);
		// Image must fill the entire stamp bbox (no split)
		$this->assertSame($imagePath, $appearance->signatureImagePath);
		$this->assertNull($appearance->signatureImageFrame);
	}

	/**
	 * Regression: in SIGNAME_AND_DESCRIPTION the signer name must be horizontally
	 * centred within the left half of the stamp, not pinned to leftPadding (left edge).
	 *
	 * Layout math for width=200, height=80, fontSize=20, name="Al":
	 *   leftHalfW      = 100.0
	 *   lineWidth      = strlen("Al") * (20 * 0.52) = 2 * 10.4 = 20.8
	 *   nameX          = max(2.0, (100 - 20.8) / 2) = 39.6  →  "39.60"
	 *   totalNameHeight = 1 * 20 * 1.0 = 20  (lineHeight factor = 1.0)
	 *   nameStartY     = (80 + 20) / 2 - 20 = 30.0           →  "30.00"
	 * Old (broken) code always used leftPadding=2.0  →  "2.00 30.00 Td"
	 */
	public function testBuildXObjectSignameAndDescriptionCentersNameInLeftHalf(): void {
		$handler = $this->getHandlerWithMode(SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION);
		$handler->setSignatureParams(['SignerCommonName' => 'Al']);
		$xObject = $this->callPrivateMethod(
			$handler, 'buildXObject', 200, 80, SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION,
		);

		// Centred position must appear
		$this->assertStringContainsString('39.60 30.00 Td', $xObject->stream);
		// Old left-aligned position must NOT appear
		$this->assertStringNotContainsString('2.00 30.00 Td', $xObject->stream);
	}

	public function testBuildXObjectSignameAndDescriptionWithEmptyNameOmitsNameBlock(): void {
		// When SignerCommonName is absent and certificate has no CN, no name block should appear
		$engine = $this->createMock(\OCA\Libresign\Handler\CertificateEngine\IEngineHandler::class);
		$engine->method('readCertificate')->willReturn(['subject' => ['CN' => '']]);
		$this->certificateEngineFactory->method('getEngine')->willReturn($engine);

		$handler = $this->getHandlerWithMode(SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION);
		$handler->setSignatureParams([]); // no SignerCommonName
		$handler->setCertificate('cert');
		$handler->setPassword('pass');

		$xObject = $this->callPrivateMethod(
			$handler, 'buildXObject', 200, 80, SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION,
		);

		// Large font (20.0) must NOT appear when there is no name to render
		$this->assertStringNotContainsString('/F1 20.00 Tf', $xObject->stream);
		// The stream may be empty or contain only description lines, but no name Tj
		$this->assertStringNotContainsString('() Tj', $xObject->stream);
	}

	/**
	 * Regression: ServerSignatureDate must be passed to signatureTextService->parse()
	 * as a valid ISO 8601 (ATOM) string so that Twig's |date() filter can parse it.
	 * Before the fix the value was "Y.m.d H:i:s UTC" which Twig's date filter
	 * would fail to parse reliably across PHP versions.
	 */
	public function testBuildXObjectPassesAtomFormatServerSignatureDateToParseContext(): void {
		$capturedContext = null;

		$signatureTextService = $this->createMock(SignatureTextService::class);
		$signatureTextService->method('getRenderMode')
			->willReturn(SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY);
		$signatureTextService->method('parse')
			->willReturnCallback(function (string $template = '', array $context = []) use (&$capturedContext): array {
				$capturedContext = $context;
				return ['parsed' => 'Signed by', 'templateFontSize' => 10.0];
			});
		$signatureTextService->method('getTemplateFontSize')->willReturn(10.0);
		$signatureTextService->method('getSignatureFontSize')->willReturn(20.0);

		$handler = new PhpNativeHandler(
			$this->appConfig,
			$this->docMdpConfigService,
			$signatureTextService,
			$this->signatureBackgroundService,
			$this->certificateEngineFactory,
		);

		$this->callPrivateMethod($handler, 'buildXObject', 100, 50, SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY);

		$this->assertArrayHasKey('ServerSignatureDate', $capturedContext);
		$serverSignatureDate = $capturedContext['ServerSignatureDate'];

		// Must be parseable as a valid date by PHP (required for Twig |date() filter)
		$parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $serverSignatureDate);
		$this->assertNotFalse(
			$parsed,
			"ServerSignatureDate must be a valid ATOM/ISO 8601 string, got: {$serverSignatureDate}"
		);
	}

	private function getHandler(): PhpNativeHandler {
		return $this->getHandlerWithMode(SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY);
	}

	private function getHandlerWithMode(string $renderMode): PhpNativeHandler {
		$this->signatureTextService->method('getRenderMode')
			->willReturn($renderMode);
		$this->signatureTextService->method('parse')
			->willReturn([
				'parsed' => 'Signed by',
				'templateFontSize' => 10.0,
			]);
		$this->signatureTextService->method('getTemplateFontSize')
			->willReturn(10.0);
		$this->signatureTextService->method('getSignatureFontSize')
			->willReturn(20.0);

		return new PhpNativeHandler(
			$this->appConfig,
			$this->docMdpConfigService,
			$this->signatureTextService,
			$this->signatureBackgroundService,
			$this->certificateEngineFactory,
		);
	}

	private function callPrivateMethod(object $instance, string $methodName, mixed ...$args): mixed {
		$method = new \ReflectionMethod($instance, $methodName);
		$method->setAccessible(true);
		return $method->invoke($instance, ...$args);
	}
}
