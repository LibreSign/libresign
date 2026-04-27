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
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\CollectMetadata\CollectMetadataPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy as SignatureTextPolicyProvider;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCP\IAppConfig;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class SignatureTextServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignatureTextService $service;
	private IAppConfig $appConfig;
	private IL10N $l10n;
	private IDateTimeZone $dateTimeZone;
	private IRequest&MockObject $request;
	private IUserSession&MockObject $userSession;
	private IURLGenerator&MockObject $urlGenerator;
	private LoggerInterface&MockObject $logger;
	private PolicyService&MockObject $policyService;
	/** @var array<string, mixed> */
	private array $policyValues = [];


	public function setUp(): void {
		$this->l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->dateTimeZone = \OCP\Server::get(IDateTimeZone::class);
		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->urlGenerator
			->method('linkToRouteAbsolute')
			->willReturnCallback(fn (string $route, array $params): string => 'https://example.test/' . $route . '/' . ($params['uuid'] ?? ''));
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->policyService = $this->createMock(PolicyService::class);
		$this->policyValues = [
			CollectMetadataPolicy::KEY => false,
			SignatureTextPolicyProvider::KEY_TEMPLATE => '',
			SignatureTextPolicyProvider::KEY_TEMPLATE_FONT_SIZE => SignatureTextService::TEMPLATE_DEFAULT_FONT_SIZE,
			SignatureTextPolicyProvider::KEY_SIGNATURE_FONT_SIZE => SignatureTextService::SIGNATURE_DEFAULT_FONT_SIZE,
			SignatureTextPolicyProvider::KEY_SIGNATURE_WIDTH => SignatureTextService::DEFAULT_SIGNATURE_WIDTH,
			SignatureTextPolicyProvider::KEY_SIGNATURE_HEIGHT => SignatureTextService::DEFAULT_SIGNATURE_HEIGHT,
			SignatureTextPolicyProvider::KEY_RENDER_MODE => SignerElementsService::RENDER_MODE_DEFAULT,
		];

		$this->policyService
			->method('resolve')
			->willReturnCallback(function (string|\BackedEnum $policyKey): ResolvedPolicy {
				$key = $policyKey instanceof \BackedEnum ? (string)$policyKey->value : $policyKey;
				$value = $this->policyValues[$key] ?? null;
				return (new ResolvedPolicy())
					->setPolicyKey($key)
					->setEffectiveValue($value);
			});

		$this->policyService
			->method('saveSystem')
			->willReturnCallback(function (string|\BackedEnum $policyKey, mixed $value): ResolvedPolicy {
				$key = $policyKey instanceof \BackedEnum ? (string)$policyKey->value : $policyKey;
				$this->policyValues[$key] = $value;
				return (new ResolvedPolicy())
					->setPolicyKey($key)
					->setEffectiveValue($value);
			});
	}

	private function getClass(): SignatureTextService {
		$this->service = new SignatureTextService(
			$this->l10n,
			$this->dateTimeZone,
			$this->request,
			$this->userSession,
			$this->urlGenerator,
			$this->logger,
			$this->policyService,
		);
		return $this->service;
	}

	public function testCollectingMetadata(): void {
		$this->policyValues[CollectMetadataPolicy::KEY] = true;

		$actual = $this->getClass()->getAvailableVariables();
		$this->assertArrayHasKey('{{SignerIP}}', $actual);
		$this->assertArrayHasKey('{{SignerUserAgent}}', $actual);
		$this->assertArrayHasKey('{{qrcode}}', $actual);
		$this->assertArrayHasKey('{{ValidationURL}}', $actual);

		$template = $this->getClass()->getDefaultTemplate();
		$this->assertStringContainsString('IP', $template);
	}

	public function testNotCollectingMetadata(): void {
		$this->policyValues[CollectMetadataPolicy::KEY] = false;

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

	#[DataProvider('providerSplitAndGetLongestHalfLength')]
	public function testSplitAndGetLongestHalfLength(string $text, int $expected): void {
		$class = $this->getClass();
		$actual = self::invokePrivate($class, 'splitAndGetLongestHalfLength', [$text]);
		$this->assertEquals($expected, $actual);
	}

	public static function providerSplitAndGetLongestHalfLength(): array {
		return [
			'empty string' => ['', mb_strlen('')],
			'single character' => ['A', mb_strlen('A')],
			'no spaces' => ['Loremipsumdolorsitamet', mb_strlen('Loremipsumdolorsitamet')],
			'space exactly in the middle' => ['Lorem ipsum', mb_strlen('Lorem')],
			'space after middle' => ['Open source rocks', mb_strlen('source rocks')],
			'space before middle' => ['Free software forever', mb_strlen('software forever')],
			'spaces on edges' => [' Leading and trailing ', mb_strlen('and trailing')],
			'unbalanced halves' => ['Short veryveryverylongword', mb_strlen('veryveryverylongword')],
			'equal halves' => ['One Two', mb_strlen('One')],
			'multiple words' => ['This is a longer string with more words', mb_strlen('This is a longer string')],
			'no possible split (no spaces)' => ['ABCDEFGHIJK', mb_strlen('ABCDEFGHIJK')],
			'only spaces' => ['     ', mb_strlen('')],
			'space at beginning' => [' HelloWorld', mb_strlen('HelloWorld')],
			'space at end' => ['HelloWorld ', mb_strlen('HelloWorld')],
			'two short words' => ['a b', mb_strlen('a')],
			'multibyte with accents' => ['João da Silva', mb_strlen('da Silva')],
			'multibyte at split' => ['José Antônio', mb_strlen('Antônio')],
			'multibyte no spaces' => ['Ñandúçara', mb_strlen('Ñandúçara')],
			'emoji in middle' => ['Smile 😀 always', mb_strlen('Smile 😀')],
			'emoji before space' => ['Good job 👍 team', mb_strlen('Good job 👍')],
			'emoji after space' => ['Team 👏 effort', mb_strlen('Team 👏')],
			'cjk characters with space' => ['漢字 漢字', mb_strlen('漢字')],
			'arabic with space' => ['سلام عليكم', mb_strlen('عليكم')],
			'emoji only' => ['😊 🙃', mb_strlen('😊')],
			'mixed accented and emoji' => ['Renée 💡 Dubois', mb_strlen('Renée 💡')],
			'greek with space' => ['Αθήνα Ελλάδα', mb_strlen('Ελλάδα')],
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
		$this->assertFileExists($fallbackFond = __DIR__ . '/../../../../3rdparty/composer/mpdf/mpdf/ttfonts/DejaVuSerifCondensed.ttf');
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

	public function testGetFullSignatureDimensionsShouldFallbackToDefaultsWhenConfigIsInvalid(): void {
		$this->policyValues[SignatureTextPolicyProvider::KEY_SIGNATURE_WIDTH] = 0.0;
		$this->policyValues[SignatureTextPolicyProvider::KEY_SIGNATURE_HEIGHT] = -1.0;

		$class = $this->getClass();

		$this->assertEquals(SignatureTextService::DEFAULT_SIGNATURE_WIDTH, $class->getFullSignatureWidth());
		$this->assertEquals(SignatureTextService::DEFAULT_SIGNATURE_HEIGHT, $class->getFullSignatureHeight());
	}
}
