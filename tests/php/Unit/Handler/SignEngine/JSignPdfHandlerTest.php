<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Handler\SignEngine;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\DataObjects\VisibleElementAssoc;
use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\SignEngine\JSignPdfHandler;
use OCA\Libresign\Helper\JavaHelper;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\DocMdp\ConfigService as DocMdpConfigService;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\CollectMetadata\CollectMetadataPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureHashAlgorithm\SignatureHashAlgorithmPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicyValue;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicy;
use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicyValue;
use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Vendor\Jeidison\JSignPDF\JSignPDF;
use OCA\Libresign\Vendor\Jeidison\JSignPDF\Sign\JSignParam;
use OCP\IAppConfig;
use OCP\IDateTimeZone;
use OCP\IRequest;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory as IL10NFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class JSignPdfHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAppConfig $appConfig;
	private LoggerInterface&MockObject $loggerInterface;
	private ITempManager $tempManager;
	private SignatureBackgroundService&MockObject $signatureBackgroundService;
	private static ?CertificateEngineFactory $certificateEngineFactory = null;
	private JavaHelper&MockObject $javaHelper;
	private static string $certificateContent = '';
	/** @var array<string, mixed> */
	private array $resolvedPolicyValues = [];
	#[\Override]
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		try {
			$appConfig = self::getMockAppConfig();
			$appConfig->setValueString(Application::APP_ID, 'certificate_engine', 'openssl');
			// The CRL distribution point of the root certificate needs a CA identifier.
			\OCP\Server::get(CaIdentifierService::class)->generateCaId('openssl');
			self::$certificateEngineFactory = \OCP\Server::get(CertificateEngineFactory::class);
			$certificateEngine = self::$certificateEngineFactory->getEngine();
			$certificateEngine
				->setConfigPath(\OCP\Server::get(ITempManager::class)->getTemporaryFolder('certificate'))
				->generateRootCert('Test Root CA', []);

			self::$certificateContent = $certificateEngine
				->setHosts(['user@email.tld'])
				->setCommonName('John Doe')
				->setPassword('password')
				->generateCertificate();
		} catch (\Throwable) {
			self::$certificateContent = '';
			self::$certificateEngineFactory = null;
		}
	}
	#[\Override]
	public function setUp(): void {
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->loggerInterface = $this->createMock(LoggerInterface::class);
		$this->tempManager = \OCP\Server::get(ITempManager::class);
		$this->signatureBackgroundService = $this->createMock(SignatureBackgroundService::class);
		$this->javaHelper = $this->createMock(JavaHelper::class);
		$this->resolvedPolicyValues = [
			CollectMetadataPolicy::KEY => false,
			SignatureTextPolicy::KEY => SignatureTextPolicyValue::encode([
				'template' => '',
				'template_font_size' => SignatureTextPolicyValue::DEFAULT_TEMPLATE_FONT_SIZE,
				'signature_font_size' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
				'signature_width' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_WIDTH,
				'signature_height' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_HEIGHT,
				'background_type' => 'default',
				'render_mode' => 'default',
			]),
			TsaPolicy::KEY => '',
			SignatureHashAlgorithmPolicy::KEY => 'SHA256',
		];
	}

	private function getInstance(array $methods = []): JSignPdfHandler|MockObject {
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator
			->method('linkToRouteAbsolute')
			->willReturnCallback(fn (string $route, array $params): string => 'https://example.test/' . $route . '/' . ($params['uuid'] ?? ''));
		$policyService = $this->createMock(PolicyService::class);
		$policyService
			->method('resolve')
			->willReturnCallback(function (string|\BackedEnum $policyKey): ResolvedPolicy {
				$key = $policyKey instanceof \BackedEnum ? (string)$policyKey->value : $policyKey;
				$value = $this->resolvedPolicyValues[$key] ?? null;

				return (new ResolvedPolicy())
					->setPolicyKey($key)
					->setEffectiveValue($value);
			});

		$signatureTextService = new SignatureTextService(
			\OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID),
			\OCP\Server::get(IDateTimeZone::class),
			\OCP\Server::get(IRequest::class),
			\OCP\Server::get(IUserSession::class),
			$urlGenerator,
			\OCP\Server::get(LoggerInterface::class),
			$policyService,
		);

		// Create mock factory if initialization failed in setUpBeforeClass
		$certificateEngineFactory = self::$certificateEngineFactory ?? $this->createMock(CertificateEngineFactory::class);

		if (empty($methods)) {
			return new JSignPdfHandler(
				$this->appConfig,
				$this->loggerInterface,
				$signatureTextService,
				$this->tempManager,
				$this->signatureBackgroundService,
				$policyService,
				$certificateEngineFactory,
				$this->javaHelper,
				$this->createMock(DocMdpConfigService::class),
			);
		}
		return $this->getMockBuilder(JSignPdfHandler::class)
			->setConstructorArgs([
				$this->appConfig,
				$this->loggerInterface,
				$signatureTextService,
				$this->tempManager,
				$this->signatureBackgroundService,
				$policyService,
				$certificateEngineFactory,
				$this->javaHelper,
				$this->createMock(DocMdpConfigService::class),
			])
			->onlyMethods($methods)
			->getMock();
	}

	private function persistSignatureStampPolicy(
		string $template,
		string $renderMode = SignerElementsService::RENDER_MODE_DEFAULT,
		float $templateFontSize = SignatureTextPolicyValue::DEFAULT_TEMPLATE_FONT_SIZE,
		float $signatureFontSize = SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
		float $signatureWidth = SignatureTextPolicyValue::DEFAULT_SIGNATURE_WIDTH,
		float $signatureHeight = SignatureTextPolicyValue::DEFAULT_SIGNATURE_HEIGHT,
		string $backgroundType = 'default',
	): void {
		$persistedRenderMode = match ($renderMode) {
			SignerElementsService::RENDER_MODE_GRAPHIC_ONLY, 'graphic' => 'graphic',
			SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY, 'description_only' => 'description_only',
			SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION, 'text' => 'text',
			default => 'default',
		};

		$this->resolvedPolicyValues[SignatureTextPolicy::KEY] = SignatureTextPolicyValue::encode([
			'template' => $template,
			'template_font_size' => $templateFontSize,
			'signature_font_size' => $signatureFontSize,
			'signature_width' => $signatureWidth,
			'signature_height' => $signatureHeight,
			'background_type' => $backgroundType,
			'render_mode' => $persistedRenderMode,
		]);
	}

	private function persistHashAlgorithmPolicy(string $algorithm): void {
		$this->resolvedPolicyValues[SignatureHashAlgorithmPolicy::KEY] = $algorithm;
	}

	private function setDocMdpConfigService(JSignPdfHandler $handler, DocMdpConfigService $docMdpConfigService): void {
		$reflection = new \ReflectionProperty(JSignPdfHandler::class, 'docMdpConfigService');
		$reflection->setValue($handler, $docMdpConfigService);
	}

	#[DataProvider('providerGetHashAlgorithm')]
	public function testGetHashAlgorithm(string $setting, string $content, string $expected): void {
		if (self::$certificateEngineFactory === null || empty(self::$certificateContent)) {
			$this->markTestSkipped('Certificate initialization failed');
		}

		$this->persistHashAlgorithmPolicy($setting);
		$instance = $this->getInstance(['getInputFile']);
		$file = $this->createMock(\OCP\Files\File::class);
		$file->method('getContent')->willReturn($content);
		$instance->method('getInputFile')->willReturn($file);
		$actual = self::invokePrivate($instance, 'getHashAlgorithm', [$content]);
		$this->assertEquals($expected, $actual);
	}

	public static function providerGetHashAlgorithm(): array {
		return [
			'empty setting, PDF 1.6' => ['', '%PDF-1.6', 'SHA256'],
			'invalid PDF header' => ['', 'random data', 'SHA256'],
			'invalid setting, fallback to SHA256 on PDF 1.7' => ['XYZ', '%PDF-1.7', 'SHA256'],
			'null-like setting, PDF 1.5' => ['0', '%PDF-1.5', 'SHA1'],
			'default with PDF 1.0' => ['', '%PDF-1', 'SHA1'],
			'SHA1 with PDF 1.5' => ['', '%PDF-1.5', 'SHA1'],
			'SHA1 with PDF 1.6' => ['', '%PDF-1.6', 'SHA256'],
			'SHA1 with PDF 1.7' => ['', '%PDF-1.7', 'SHA256'],
			'SHA1 with PDF 2.0' => ['', '%PDF-2.0', 'SHA256'],
			'SHA384, PDF 1.6 (fallback)' => ['SHA384', '%PDF-1.6', 'SHA256'],
			'SHA384, PDF 1.7' => ['SHA384', '%PDF-1.7', 'SHA384'],
			'SHA512, PDF 1.6' => ['SHA512', '%PDF-1.6', 'SHA256'],
			'RIPEMD160, PDF 1.6 (unsupported)' => ['RIPEMD160', '%PDF-1.6', 'SHA256'],
			'RIPEMD160, PDF 1.7 (supported)' => ['RIPEMD160', '%PDF-1.7', 'RIPEMD160'],
		];
	}

	#[DataProvider('providerExtractPdfVersion')]
	public function testExtractPdfVersion(string $content, ?float $expected): void {
		if (self::$certificateEngineFactory === null || empty(self::$certificateContent)) {
			$this->markTestSkipped('Certificate initialization failed');
		}

		$instance = $this->getInstance();
		$actual = self::invokePrivate($instance, 'extractPdfVersion', [$content]);
		$this->assertEquals($expected, $actual);
	}

	public static function providerExtractPdfVersion(): array {
		return [
			'PDF 1.0' => ['%PDF-1.0', 1.0],
			'PDF 1.1' => ['%PDF-1.1', 1.1],
			'PDF 1.2' => ['%PDF-1.2', 1.2],
			'PDF 1.3' => ['%PDF-1.3', 1.3],
			'PDF 1.4' => ['%PDF-1.4', 1.4],
			'PDF 1.5' => ['%PDF-1.5', 1.5],
			'PDF 1.6' => ['%PDF-1.6', 1.6],
			'PDF 1.7' => ['%PDF-1.7', 1.7],
			'PDF 2.0' => ['%PDF-2.0', 2.0],
			'Invalid header' => ['random data', null],
			'No version' => ['%PDF-', null],
			'Empty string' => ['', null],
			'With content after' => ["%PDF-1.6\n%âãÏÓ", 1.6],
		];
	}

	#[DataProvider('providerNormalizePdfVersion')]
	public function testNormalizePdfVersion(string $hashAlgorithm, string $content, string $expectedStart): void {
		if (self::$certificateEngineFactory === null || empty(self::$certificateContent)) {
			$this->markTestSkipped('Certificate initialization failed');
		}

		$this->persistHashAlgorithmPolicy($hashAlgorithm ?? '');
		$instance = $this->getInstance();
		$actual = self::invokePrivate($instance, 'normalizePdfVersion', [$content]);
		$this->assertStringStartsWith($expectedStart, $actual);
	}

	public static function providerNormalizePdfVersion(): array {
		return [
			'PDF 1.0 upgraded to 1.3 (JSignPDF NullPointerException workaround)' => ['SHA256', '%PDF-1.0', '%PDF-1.3'],
			'PDF 1.1 upgraded to 1.3 (JSignPDF NullPointerException workaround)' => ['SHA256', '%PDF-1.1', '%PDF-1.3'],
			'PDF 1.2 upgraded to 1.6 for SHA256' => ['SHA256', '%PDF-1.2', '%PDF-1.6'],
			'PDF 1.3 upgraded to 1.6 for SHA256' => ['SHA256', '%PDF-1.3', '%PDF-1.6'],
			'PDF 1.4 upgraded to 1.6 for SHA256' => ['SHA256', '%PDF-1.4', '%PDF-1.6'],
			'PDF 1.5 upgraded to 1.6 for SHA256' => ['SHA256', '%PDF-1.5', '%PDF-1.6'],
			'PDF 1.5 not changed with SHA1' => ['SHA1', '%PDF-1.5', '%PDF-1.5'],
			'PDF 1.6 not changed' => ['SHA256', '%PDF-1.6', '%PDF-1.6'],
			'PDF 1.7 not changed' => ['SHA256', '%PDF-1.7', '%PDF-1.7'],
			'PDF 2.0 not changed' => ['SHA256', '%PDF-2.0', '%PDF-2.0'],
			'Invalid PDF not changed' => ['SHA256', 'random data', 'random data'],
		];
	}

	#[DataProvider('providerNormalizeScaleFactor')]
	public function testNormalizeScaleFactor(float $input, float $expected): void {
		if (self::$certificateEngineFactory === null || empty(self::$certificateContent)) {
			$this->markTestSkipped('Certificate initialization failed');
		}

		$instance = $this->getInstance();
		$actual = self::invokePrivate($instance, 'normalizeScaleFactor', [$input]);
		$this->assertEquals($expected, $actual);
	}

	public static function providerNormalizeScaleFactor(): array {
		return [
			'Below minimum (0)' => [0.0, 5.0],
			'Below minimum (3.5)' => [3.5, 5.0],
			'Below minimum (4.9)' => [4.9, 5.0],
			'At minimum' => [5.0, 5.0],
			'Above minimum (5.1)' => [5.1, 5.1],
			'Above minimum (10.0)' => [10.0, 10.0],
			'Large scale' => [100.0, 100.0],
		];
	}

	#[DataProvider('providerSignAffectedParams')]
	public function testSignAffectedParams(
		array $visibleElements,
		float $signatureWidth,
		float $signatureHeight,
		string $template,
		string $signatureBackgroundType,
		string $renderMode,
		float $templateFontSize,
		string $pdfContent,
		?string $hashAlgorithm,
		array $params,
	):void {
		if (self::$certificateEngineFactory === null || empty(self::$certificateContent)) {
			$this->markTestSkipped('Certificate initialization failed');
		}

		$inputFile = $this->createMock(\OC\Files\Node\File::class);
		$inputFile->method('getContent')
			->willReturn($pdfContent);
		$paramsSeen = [];
		$mock = $this->createMock(JSignPDF::class);
		$mock->method('setParam')
			->willReturnCallback(function (JSignParam $param) use (&$paramsSeen): void {
				$paramsSeen[] = $param->getJSignParameters();
			});
		$mock->method('sign')->willReturn('content');

		$this->signatureBackgroundService->method('getSignatureBackgroundType')->willReturn(
			$signatureBackgroundType
		);

		$this->signatureBackgroundService->method('getImagePath')->willReturn(
			realpath(__DIR__ . '/../../../../../img/LibreSign.png')
		);

		$this->persistSignatureStampPolicy(
			$template,
			$renderMode,
			$templateFontSize,
			SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
			$signatureWidth,
			$signatureHeight,
			$signatureBackgroundType,
		);
		$this->persistHashAlgorithmPolicy($hashAlgorithm ?? '');
		$this->appConfig->setValueString('libresign', 'java_path', __FILE__);
		$this->appConfig->setValueString('libresign', 'jsignpdf_temp_path', sys_get_temp_dir());
		$this->appConfig->setValueString('libresign', 'jsignpdf_path', __DIR__);

		$jSignPdfHandler = $this->getInstance();
		$jSignPdfHandler->setVisibleElements($visibleElements);
		$jSignPdfHandler->setJSignPdf($mock);
		$jSignPdfHandler->setInputFile($inputFile);
		$jSignPdfHandler->setSignatureParams(['SignerCommonName' => 'Test User']);
		$jSignPdfHandler->setCertificate(self::$certificateContent);
		$jSignPdfHandler->setPassword('password');
		$actual = $jSignPdfHandler->getSignedContent();
		$this->assertEquals('content', $actual);
		$this->assertEquals('password', $jSignPdfHandler->getJSignParam()->getPassword());
		$this->assertCount(1, $paramsSeen);
		$paramsAsOptions = preg_replace('/\\/\S+_merged.png/', 'merged.png', $paramsSeen[0]);
		$paramsAsOptions = preg_replace('/\\/\S+_text_image.png/', 'text_image.png', (string)$paramsAsOptions);
		$paramsAsOptions = preg_replace('/\\/\S+_background.png/', 'background.png', (string)$paramsAsOptions);
		$paramsAsOptions = preg_replace('/\\/\S+app-dark.png/', 'signature.png', (string)$paramsAsOptions);
		$this->assertSame(self::expectedJSignParameters($params), $paramsAsOptions);
	}

	/**
	 * What JSignParam::getJSignParameters() renders: the wrapper defaults
	 * followed by the given options, each option and value escaped for the
	 * shell, flags as bare escaped tokens.
	 */
	private static function expectedJSignParameters(array $params): string {
		$tokens = [];
		foreach (array_merge(['-a', '-kst' => 'PKCS12'], $params) as $option => $value) {
			$tokens[] = is_string($option)
				? escapeshellarg($option) . ' ' . escapeshellarg($value)
				: escapeshellarg($value);
		}
		return implode(' ', $tokens);
	}

	public static function providerSignAffectedParams(): array {
		return [
			'without visible elements' => [
				'visibleElements' => [],
				'signatureWidth' => 100,
				'signatureHeight' => 100,
				'template' => '',
				'signatureBackgroundType' => '',
				'renderMode' => '',
				'templateFontSize' => 0,
				'pdfContent' => '%PDF-1',
				'hashAlgorithm' => '',
				'params' => ['--hash-algorithm' => 'SHA1'],
			],
			'page = 1 is default, do not will set the page' => [
				'visibleElements' => [self::getElement([
					'page' => 1,
					'llx' => 0,
					'lly' => 0,
					'urx' => 0,
					'ury' => 0,
				], realpath(__DIR__ . '/../../../../../img/app-dark.png'))],
				'signatureWidth' => 100,
				'signatureHeight' => 100,
				'template' => '',
				'signatureBackgroundType' => 'default',
				'renderMode' => SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
				'templateFontSize' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--hash-algorithm' => 'SHA256', '--l2-text' => '', '-V', '-llx' => '0', '-lly' => '0', '-urx' => '0', '-ury' => '0', '--bg-path' => 'merged.png']
			],
			'page != 1: will have pg; without template: l2-text empty' => [
				'visibleElements' => [self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				], realpath(__DIR__ . '/../../../../../img/app-dark.png'))],
				'signatureWidth' => 20,
				'signatureHeight' => 20,
				'template' => '',
				'signatureBackgroundType' => 'default',
				'renderMode' => SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
				'templateFontSize' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--hash-algorithm' => 'SHA256', '--l2-text' => '', '-V', '-pg' => '2', '-llx' => '10', '-lly' => '20', '-urx' => '30', '-ury' => '40', '--bg-path' => 'merged.png']
			],
			'with template we have the l2-text' => [
				'visibleElements' => [self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				], realpath(__DIR__ . '/../../../../../img/app-dark.png'))],
				'signatureWidth' => 20,
				'signatureHeight' => 20,
				'template' => 'aaaaa',
				'signatureBackgroundType' => 'default',
				'renderMode' => SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
				'templateFontSize' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--l2-text' => 'aaaaa', '-V', '-pg' => '2', '-llx' => '10', '-lly' => '20', '-urx' => '30', '-ury' => '40', '--bg-path' => 'background.png', '--hash-algorithm' => 'SHA256']
			],
			'font size != default font size: emits --font-size' => [
				'visibleElements' => [self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				], realpath(__DIR__ . '/../../../../../img/app-dark.png'))],
				'signatureWidth' => 20,
				'signatureHeight' => 20,
				'template' => 'aaaaa',
				'signatureBackgroundType' => 'default',
				'renderMode' => SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
				'templateFontSize' => 11,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--l2-text' => 'aaaaa', '-V', '-pg' => '2', '-llx' => '10', '-lly' => '20', '-urx' => '30', '-ury' => '40', '--font-size' => '11', '--bg-path' => 'background.png', '--hash-algorithm' => 'SHA256']
			],
			'background = deleted: bg-path = signature' => [
				'visibleElements' => [self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				], realpath(__DIR__ . '/../../../../../img/app-dark.png'))],
				'signatureWidth' => 20,
				'signatureHeight' => 20,
				'template' => 'aaaaa',
				'signatureBackgroundType' => 'deleted',
				'renderMode' => SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
				'templateFontSize' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--l2-text' => 'aaaaa', '-V', '-pg' => '2', '-llx' => '10', '-lly' => '20', '-urx' => '30', '-ury' => '40', '--bg-path' => 'signature.png', '--hash-algorithm' => 'SHA256']
			],
			'template with shell special characters reaches the wrapper unescaped' => [
				'visibleElements' => [self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				], realpath(__DIR__ . '/../../../../../img/app-dark.png'))],
				'signatureWidth' => 20,
				'signatureHeight' => 20,
				'template' => 'a"b $c \'d e',
				'signatureBackgroundType' => 'deleted',
				'renderMode' => SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
				'templateFontSize' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--l2-text' => 'a"b $c \'d e', '-V', '-pg' => '2', '-llx' => '10', '-lly' => '20', '-urx' => '30', '-ury' => '40', '--bg-path' => 'signature.png', '--hash-algorithm' => 'SHA256'],
			],
			'font size != default but no template: no --font-size' => [
				'visibleElements' => [self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				], realpath(__DIR__ . '/../../../../../img/app-dark.png'))],
				'signatureWidth' => 20,
				'signatureHeight' => 20,
				'template' => '',
				'signatureBackgroundType' => 'default',
				'renderMode' => SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
				'templateFontSize' => 11,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--hash-algorithm' => 'SHA256', '--l2-text' => '', '-V', '-pg' => '2', '-llx' => '10', '-lly' => '20', '-urx' => '30', '-ury' => '40', '--bg-path' => 'merged.png'],
			],
			'background and template, bg-path = background, img-path = signature' => [
				'visibleElements' => [self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				], realpath(__DIR__ . '/../../../../../img/app-dark.png'))],
				'signatureWidth' => 20,
				'signatureHeight' => 20,
				'template' => 'aaaaa',
				'signatureBackgroundType' => 'default',
				'renderMode' => SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION,
				'templateFontSize' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--l2-text' => 'aaaaa', '-V', '-pg' => '2', '-llx' => '10', '-lly' => '20', '-urx' => '30', '-ury' => '40', '--render-mode' => 'GRAPHIC_AND_DESCRIPTION', '--bg-path' => 'background.png', '--img-path' => 'signature.png', '--hash-algorithm' => 'SHA256']
			],
			'background and template, render mode equals to SIGNAME_AND_DESCRIPTION: bg-path = background, img-path = text_image' => [
				'visibleElements' => [self::getElement([
					'page' => 2,
					'llx' => 1,
					'lly' => 100,
					'urx' => 351,
					'ury' => 200,
				], realpath(__DIR__ . '/../../../../../img/app-dark.png'))],
				'signatureWidth' => 350,
				'signatureHeight' => 100,
				'template' => 'aaaaa',
				'signatureBackgroundType' => 'default',
				'renderMode' => SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION,
				'templateFontSize' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--l2-text' => 'aaaaa', '-V', '-pg' => '2', '-llx' => '1', '-lly' => '100', '-urx' => '351', '-ury' => '200', '--render-mode' => 'GRAPHIC_AND_DESCRIPTION', '--bg-path' => 'background.png', '--img-path' => 'text_image.png', '--hash-algorithm' => 'SHA256']
			],
			'template without background; with signature image; render-mode: SIGNAME_AND_DESCRIPTION' => [
				'visibleElements' => [self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				], realpath(__DIR__ . '/../../../../../img/app-dark.png'))],
				'signatureWidth' => 20,
				'signatureHeight' => 20,
				'template' => 'aaaaa',
				'signatureBackgroundType' => 'deleted',
				'renderMode' => SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION,
				'templateFontSize' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--l2-text' => 'aaaaa', '-V', '-pg' => '2', '-llx' => '10', '-lly' => '20', '-urx' => '30', '-ury' => '40', '--render-mode' => 'GRAPHIC_AND_DESCRIPTION', '--img-path' => 'text_image.png', '--hash-algorithm' => 'SHA256']
			],
			'template without background; without signature image; render-mode: SIGNAME_AND_DESCRIPTION' => [
				'visibleElements' => [self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				], '')],
				'signatureWidth' => 20,
				'signatureHeight' => 20,
				'template' => 'aaaaa',
				'signatureBackgroundType' => 'deleted',
				'renderMode' => SignerElementsService::RENDER_MODE_SIGNAME_AND_DESCRIPTION,
				'templateFontSize' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--l2-text' => 'aaaaa', '-V', '-pg' => '2', '-llx' => '10', '-lly' => '20', '-urx' => '30', '-ury' => '40', '--render-mode' => 'GRAPHIC_AND_DESCRIPTION', '--img-path' => 'text_image.png', '--hash-algorithm' => 'SHA256']
			],
			// Regression: background with GRAPHIC_AND_DESCRIPTION but NO user signature image.
			// Before the fix, mergeBackgroundWithSignature('...', '') crashed with new Imagick('').
			// Now the background is used directly and no --img-path is emitted.
			'GRAPHIC_AND_DESCRIPTION with background but no signature image: bg-path = background, no img-path' => [
				'visibleElements' => [self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				], '')], // empty imagePath — clickToSign scenario
				'signatureWidth' => 20,
				'signatureHeight' => 20,
				'template' => 'aaaaa',
				'signatureBackgroundType' => 'default',
				'renderMode' => SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION,
				'templateFontSize' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--l2-text' => 'aaaaa', '-V', '-pg' => '2', '-llx' => '10', '-lly' => '20', '-urx' => '30', '-ury' => '40', '--render-mode' => 'GRAPHIC_AND_DESCRIPTION', '--bg-path' => 'background.png', '--hash-algorithm' => 'SHA256']
			],
			'background without template: bg-path = merged with signature, without img-path' => [
				'visibleElements' => [self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				], realpath(__DIR__ . '/../../../../../img/app-dark.png'))],
				'signatureWidth' => 20,
				'signatureHeight' => 20,
				'template' => '',
				'signatureBackgroundType' => 'default',
				'renderMode' => SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION,
				'templateFontSize' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--hash-algorithm' => 'SHA256', '--l2-text' => '', '-V', '-pg' => '2', '-llx' => '10', '-lly' => '20', '-urx' => '30', '-ury' => '40', '--bg-path' => 'merged.png']
			],
			'regression: invalid stored dimensions should fallback to defaults and keep signing flow' => [
				'visibleElements' => [self::getElement([
					'page' => 2,
					'llx' => 10,
					'lly' => 20,
					'urx' => 30,
					'ury' => 40,
				], realpath(__DIR__ . '/../../../../../img/app-dark.png'))],
				'signatureWidth' => 0,
				'signatureHeight' => 0,
				'template' => '',
				'signatureBackgroundType' => 'default',
				'renderMode' => SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION,
				'templateFontSize' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
				'pdfContent' => '%PDF-1.6',
				'hashAlgorithm' => '',
				'params' => ['--hash-algorithm' => 'SHA256', '--l2-text' => '', '-V', '-pg' => '2', '-llx' => '10', '-lly' => '20', '-urx' => '30', '-ury' => '40', '--bg-path' => 'merged.png']
			],
		];
	}

	public function testDocMdpAppliedOnlyOnFirstVisibleElement(): void {
		if (self::$certificateEngineFactory === null || empty(self::$certificateContent)) {
			$this->markTestSkipped('Certificate initialization failed');
		}

		$inputFile = $this->createMock(\OC\Files\Node\File::class);
		$inputFile->method('getContent')->willReturn('%PDF-1.6');

		$this->signatureBackgroundService->method('getSignatureBackgroundType')->willReturn('deleted');
		$this->signatureBackgroundService->method('getImagePath')->willReturn(
			realpath(__DIR__ . '/../../../../../img/LibreSign.png')
		);

		$this->persistSignatureStampPolicy('', SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY, 10, SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE, 100, 100);
		$this->persistHashAlgorithmPolicy('');
		$this->appConfig->setValueString('libresign', 'java_path', __FILE__);
		$this->appConfig->setValueString('libresign', 'jsignpdf_temp_path', sys_get_temp_dir());
		$this->appConfig->setValueString('libresign', 'jsignpdf_path', __DIR__);

		$paramsSeen = [];
		$mock = $this->createMock(JSignPDF::class);
		$mock->expects($this->exactly(2))
			->method('setParam')
			->willReturnCallback(function (JSignParam $param) use (&$paramsSeen): void {
				$paramsSeen[] = $param->getJSignParameters();
			});
		$mock->method('sign')->willReturn('content');

		$docMdpConfigService = $this->createMock(DocMdpConfigService::class);
		$docMdpConfigService->method('isEnabled')->willReturn(true);
		$docMdpConfigService->method('getLevel')->willReturn(DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS);

		$jSignPdfHandler = $this->getInstance();
		$this->setDocMdpConfigService($jSignPdfHandler, $docMdpConfigService);
		$jSignPdfHandler->setVisibleElements([
			self::getElement([
				'page' => 1,
				'llx' => 10,
				'lly' => 10,
				'urx' => 110,
				'ury' => 60,
			], realpath(__DIR__ . '/../../../../../img/app-dark.png')),
			self::getElement([
				'page' => 1,
				'llx' => 120,
				'lly' => 10,
				'urx' => 220,
				'ury' => 60,
			], realpath(__DIR__ . '/../../../../../img/app-dark.png')),
		]);
		$jSignPdfHandler->setJSignPdf($mock);
		$jSignPdfHandler->setInputFile($inputFile);
		$jSignPdfHandler->setSignatureParams(['SignerCommonName' => 'Test User']);
		$jSignPdfHandler->setCertificate(self::$certificateContent);
		$jSignPdfHandler->setPassword('password');

		$jSignPdfHandler->getSignedContent();

		$this->assertCount(2, $paramsSeen);
		$this->assertStringContainsString("'-cl' '" . DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->name . "'", $paramsSeen[0]);
		$this->assertStringNotContainsString("'-cl' '" . DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->name . "'", $paramsSeen[1]);
	}

	public function testDocMdpSkippedWhenSignatureExists(): void {
		if (self::$certificateEngineFactory === null || empty(self::$certificateContent)) {
			$this->markTestSkipped('Certificate initialization failed');
		}

		$inputFile = $this->createMock(\OC\Files\Node\File::class);
		$inputFile->method('getContent')->willReturn('%PDF-1.6\n/ByteRange [0 0 0 0]');

		$this->signatureBackgroundService->method('getSignatureBackgroundType')->willReturn('deleted');
		$this->signatureBackgroundService->method('getImagePath')->willReturn(
			realpath(__DIR__ . '/../../../../../img/LibreSign.png')
		);

		$this->persistSignatureStampPolicy('', SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY, 10, SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE, 100, 100);
		$this->persistHashAlgorithmPolicy('');
		$this->appConfig->setValueString('libresign', 'java_path', __FILE__);
		$this->appConfig->setValueString('libresign', 'jsignpdf_temp_path', sys_get_temp_dir());
		$this->appConfig->setValueString('libresign', 'jsignpdf_path', __DIR__);

		$paramsSeen = [];
		$mock = $this->createMock(JSignPDF::class);
		$mock->expects($this->once())
			->method('setParam')
			->willReturnCallback(function (JSignParam $param) use (&$paramsSeen): void {
				$paramsSeen[] = $param->getJSignParameters();
			});
		$mock->method('sign')->willReturn('content');

		$docMdpConfigService = $this->createMock(DocMdpConfigService::class);
		$docMdpConfigService->method('isEnabled')->willReturn(true);
		$docMdpConfigService->method('getLevel')->willReturn(DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS);

		$jSignPdfHandler = $this->getInstance();
		$this->setDocMdpConfigService($jSignPdfHandler, $docMdpConfigService);
		$jSignPdfHandler->setVisibleElements([
			self::getElement([
				'page' => 1,
				'llx' => 10,
				'lly' => 10,
				'urx' => 110,
				'ury' => 60,
			], realpath(__DIR__ . '/../../../../../img/app-dark.png')),
		]);
		$jSignPdfHandler->setJSignPdf($mock);
		$jSignPdfHandler->setInputFile($inputFile);
		$jSignPdfHandler->setSignatureParams(['SignerCommonName' => 'Test User']);
		$jSignPdfHandler->setCertificate(self::$certificateContent);
		$jSignPdfHandler->setPassword('password');

		$jSignPdfHandler->getSignedContent();

		$this->assertCount(1, $paramsSeen);
		$this->assertStringNotContainsString("'-cl' '" . DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->name . "'", $paramsSeen[0]);
	}

	#[DataProvider('providerSignatureDimensions')]
	public function testMergeBackgroundWithSignatureFitsOversizedSignatureInsideStampBox(int $signatureWidth, int $signatureHeight): void {
		if (!extension_loaded('imagick')) {
			$this->markTestSkipped('Extension imagick is not loaded');
		}

		$stampWidth = SignatureTextPolicyValue::DEFAULT_SIGNATURE_WIDTH;
		$stampHeight = SignatureTextPolicyValue::DEFAULT_SIGNATURE_HEIGHT;
		$scaleFactor = (float)(new \ReflectionClass(JSignPdfHandler::class))->getConstant('SCALE_FACTOR_MIN');
		$this->persistSignatureStampPolicy('', signatureWidth: $stampWidth, signatureHeight: $stampHeight);

		$backgroundPath = $this->createTransparentPng(10, 10);
		$signaturePath = $this->createPngWithOpaqueCorners($signatureWidth, $signatureHeight);

		$mergedPath = self::invokePrivate($this->getInstance(), 'mergeBackgroundWithSignature', [
			$backgroundPath,
			$signaturePath,
			$scaleFactor,
		]);

		$canvasWidth = (int)round($stampWidth * $scaleFactor);
		$canvasHeight = (int)round($stampHeight * $scaleFactor);
		$fitRatio = min($canvasWidth / $signatureWidth, $canvasHeight / $signatureHeight);
		$fittedWidth = (int)round($signatureWidth * $fitRatio);
		$fittedHeight = (int)round($signatureHeight * $fitRatio);
		$offsetX = intdiv($canvasWidth - $fittedWidth, 2);
		$offsetY = intdiv($canvasHeight - $fittedHeight, 2);
		$probeInset = 10;

		$merged = new \Imagick((string)$mergedPath);
		$this->assertSame($canvasWidth, $merged->getImageWidth());
		$this->assertSame($canvasHeight, $merged->getImageHeight());
		$this->assertGreaterThan(
			0,
			$merged->getImagePixelColor($offsetX + $probeInset, $offsetY + $probeInset)
				->getColorValue(\Imagick::COLOR_ALPHA),
			'Top left corner of the signature must remain inside the stamp box'
		);
		$this->assertGreaterThan(
			0,
			$merged->getImagePixelColor($offsetX + $fittedWidth - $probeInset, $offsetY + $fittedHeight - $probeInset)
				->getColorValue(\Imagick::COLOR_ALPHA),
			'Bottom right corner of the signature must remain inside the stamp box'
		);
		$merged->clear();
	}

	public static function providerSignatureDimensions(): array {
		return [
			'same aspect ratio' => [1400, 400],
			'wider signature' => [1400, 200],
			'taller signature' => [700, 800],
		];
	}

	private function createTransparentPng(int $width, int $height): string {
		$image = new \Imagick();
		$image->newImage($width, $height, new \ImagickPixel('transparent'));
		$image->setImageFormat('png32');
		$path = (string)$this->tempManager->getTemporaryFile('.png');
		$image->writeImage($path);
		$image->clear();
		return $path;
	}

	private function createPngWithOpaqueCorners(int $width, int $height): string {
		$image = new \Imagick();
		$image->newImage($width, $height, new \ImagickPixel('transparent'));
		$image->setImageFormat('png32');
		$cornerSize = 40;
		$draw = new \ImagickDraw();
		$draw->setFillColor(new \ImagickPixel('black'));
		$draw->rectangle(0, 0, $cornerSize, $cornerSize);
		$draw->rectangle($width - $cornerSize, 0, $width, $cornerSize);
		$draw->rectangle(0, $height - $cornerSize, $cornerSize, $height);
		$draw->rectangle($width - $cornerSize, $height - $cornerSize, $width, $height);
		$image->drawImage($draw);
		$path = (string)$this->tempManager->getTemporaryFile('.png');
		$image->writeImage($path);
		$image->clear();
		return $path;
	}

	private static function getElement(array $attributes = [], string $imagePath = ''): VisibleElementAssoc {
		$element = new FileElement();
		foreach ($attributes as $attribute => $value) {
			$method = 'set' . ucfirst((string)$attribute);
			$element->$method($value);
		}
		return new VisibleElementAssoc(
			$element,
			$imagePath,
		);
	}

	#[DataProvider('providerGetJSignParam')]
	public function testGetJSignParam(string $temp_path, string $java_path, string $jsignpdf_path, bool $throwException): void {
		$this->appConfig->setValueString('libresign', 'jsignpdf_home', '/');
		$this->appConfig->setValueString('libresign', 'java_path', $java_path);
		$this->appConfig->setValueString('libresign', 'jsignpdf_temp_path', $temp_path);
		$this->appConfig->setValueString('libresign', 'jsignpdf_path', $jsignpdf_path);
		$this->javaHelper->method('getJavaPath')->willReturn($java_path);

		$jSignPdfHandler = $this->getInstance();
		if ($throwException) {
			$this->expectException(\Exception::class);
			$jSignPdfHandler->getJSignParam();
			return;
		}
		$jSignParam = $jSignPdfHandler->getJSignParam();
		$this->assertSame('', $jSignParam->getPdf());
		if ($java_path === '') {
			$this->assertTrue($jSignParam->isUseJavaInstalled());
		} else {
			$this->assertFalse($jSignParam->isUseJavaInstalled());
			$this->assertSame($java_path, $jSignParam->getJavaPath());
		}
		$this->assertSame($temp_path, $jSignParam->getTempPath());
		$this->assertSame($jsignpdf_path, $jSignParam->getJSignPdfPath());
		$this->assertSame(['-Duser.home=/'], $jSignParam->getJavaOptions());
		$this->assertSame(['JSIGNPDF_HOME' => '/'], $jSignParam->getEnvironmentVariables());
		$this->assertSame("'-a' '-kst' 'PKCS12'", $jSignParam->getJSignParameters());
	}

	public static function providerGetJSignParam(): array {
		return [
			'temp path empty' => ['', '', __DIR__, true],
			'temp path not writable' => ['invalid', '', __DIR__, true],
			'system java' => [sys_get_temp_dir(), '', __DIR__, false],
			'java binary missing' => [sys_get_temp_dir(), 'b', __DIR__, true],
			'downloaded java' => [sys_get_temp_dir(), __FILE__, __DIR__, false],
			'jsignpdf path not configured' => [sys_get_temp_dir(), __FILE__, '', true],
			'jsignpdf path is a file, not the extracted directory' => [sys_get_temp_dir(), __FILE__, __FILE__, true],
		];
	}

	#[DataProvider('providerGetSignatureText')]
	public function testGetSignatureText(string $renderMode, string $template, string $expected): void {
		$this->persistSignatureStampPolicy($template, $renderMode);
		$jSignPdfHandler = $this->getInstance();
		$actual = $jSignPdfHandler->getSignatureText();
		$this->assertEquals($expected, $actual);
	}

	public function testGetSignatureTextWithTwigDateFilterAndTimezone(): void {
		$this->persistSignatureStampPolicy(
			'{{ ServerSignatureDate|date("d/m/Y H:i:s T", "Europe/Paris") }}',
			SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
		);

		$jSignPdfHandler = $this->getInstance();
		$actual = $jSignPdfHandler->getSignatureText();

		$this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2} [A-Z]{3,4}$/', $actual);
	}

	public function testGetSignatureTextWithTwigDateFilterWithoutTimezone(): void {
		$this->persistSignatureStampPolicy(
			'{{ ServerSignatureDate|date("d/m/Y") }}',
			SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
		);

		$jSignPdfHandler = $this->getInstance();
		$actual = $jSignPdfHandler->getSignatureText();

		$this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}$/', $actual);
	}

	public function testGetSignatureTextGraphicOnlyWithTwigDateFilterAlwaysReturnsEmpty(): void {
		$this->persistSignatureStampPolicy(
			'{{ ServerSignatureDate|date("d/m/Y H:i:s T", "Europe/Paris") }}',
			SignerElementsService::RENDER_MODE_GRAPHIC_ONLY,
		);

		$jSignPdfHandler = $this->getInstance();
		$actual = $jSignPdfHandler->getSignatureText();

		$this->assertSame('', $actual);
	}

	public static function providerGetSignatureText(): array {
		return [
			// The text reaches the wrapper as is: the wrapper escapes it for the shell.
			[SignerElementsService::RENDER_MODE_DEFAULT, '',     ''],
			[SignerElementsService::RENDER_MODE_DEFAULT, 'a',    'a'],
			[SignerElementsService::RENDER_MODE_DEFAULT, "a\na", "a\na"],
			[SignerElementsService::RENDER_MODE_DEFAULT, 'a"a',  'a"a'],
			[SignerElementsService::RENDER_MODE_DEFAULT, "a'a",  "a'a"],
			[SignerElementsService::RENDER_MODE_DEFAULT, 'a$a',  'a$a'],
			// Plain {{ServerSignatureDate}} (no spaces) preserves JSign placeholder
			[SignerElementsService::RENDER_MODE_DEFAULT, '{{ServerSignatureDate}}', '${timestamp}'],
			// Plain {{ ServerSignatureDate }} (with spaces) also preserves JSign placeholder
			[SignerElementsService::RENDER_MODE_DEFAULT, '{{ ServerSignatureDate }}', '${timestamp}'],
			[SignerElementsService::RENDER_MODE_GRAPHIC_ONLY, '',     ''],
			[SignerElementsService::RENDER_MODE_GRAPHIC_ONLY, 'a',    ''],
			[SignerElementsService::RENDER_MODE_GRAPHIC_ONLY, "a\na", ''],
			[SignerElementsService::RENDER_MODE_GRAPHIC_ONLY, 'a"a',  ''],
			[SignerElementsService::RENDER_MODE_GRAPHIC_ONLY, 'a$a',  ''],
		];
	}

	public function testCheckTsaErrorInvalidTsaMentionsDnsNetworkFirewall(): void {
		$jSignPdfHandler = $this->getInstance();

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Timestamp Authority (TSA) service is unavailable. Check DNS/network/firewall connectivity from this server: https://invalid-tsa.example.com/tsr');

		self::invokePrivate($jSignPdfHandler, 'checkTsaError', [
			"Invalid TSA 'https://invalid-tsa.example.com/tsr'",
		]);
	}

	public function testCheckTsaErrorUnknownHostMentionsDnsNetworkFirewall(): void {
		$jSignPdfHandler = $this->getInstance();

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage("Timestamp Authority (TSA) service error.\nCheck TSA endpoint and DNS/network/firewall connectivity from this server.");

		self::invokePrivate($jSignPdfHandler, 'checkTsaError', [
			'TSAClientBouncyCastle: java.net.UnknownHostException: invalid-tsa.example.com',
		]);
	}

	#[DataProvider('providerTsaParameters')]
	public function testTsaParametersAndPassword(array $tsaSettings, string $storedPassword, array $expectedParameters, array $expectedPasswords): void {
		if (self::$certificateEngineFactory === null || empty(self::$certificateContent)) {
			$this->markTestSkipped('Certificate initialization failed');
		}
		$this->resolvedPolicyValues[TsaPolicy::KEY] = TsaPolicyValue::encode($tsaSettings);
		$this->appConfig->setValueString('libresign', TsaPolicy::PASSWORD_APP_CONFIG_KEY, $storedPassword);
		$this->appConfig->setValueString('libresign', 'java_path', __FILE__);
		$this->appConfig->setValueString('libresign', 'jsignpdf_temp_path', sys_get_temp_dir());
		$this->appConfig->setValueString('libresign', 'jsignpdf_path', __DIR__);
		$this->persistHashAlgorithmPolicy('SHA256');

		$inputFile = $this->createMock(\OC\Files\Node\File::class);
		$inputFile->method('getContent')->willReturn('%PDF-1.6');

		$paramsSeen = [];
		$mock = $this->createMock(JSignPDF::class);
		$mock->method('setParam')
			->willReturnCallback(function (JSignParam $param) use (&$paramsSeen): void {
				$paramsSeen[] = $param;
			});
		$mock->method('sign')->willReturn('content');

		$jSignPdfHandler = $this->getInstance();
		$jSignPdfHandler->setJSignPdf($mock);
		$jSignPdfHandler->setInputFile($inputFile);
		$jSignPdfHandler->setCertificate(self::$certificateContent);
		$jSignPdfHandler->setPassword('password');
		$jSignPdfHandler->getSignedContent();

		$this->assertCount(1, $paramsSeen);
		$this->assertSame(
			self::expectedJSignParameters($expectedParameters + ['--hash-algorithm' => 'SHA256']),
			$paramsSeen[0]->getJSignParameters(),
		);
		$this->assertSame($expectedPasswords, $paramsSeen[0]->getPasswords());
		if ($storedPassword !== '') {
			$this->assertStringNotContainsString($storedPassword, $paramsSeen[0]->getJSignParameters());
		}
	}

	public static function providerTsaParameters(): array {
		$tsa = [
			'url' => 'https://tsa.example.test/tsr',
			'policy_oid' => '1.2.3.4',
			'auth_type' => 'basic',
			'username' => 'alice',
		];
		return [
			'no TSA configured' => [
				['url' => ''],
				'tsa secret',
				[],
				[],
			],
			'url only' => [
				['url' => 'https://tsa.example.test/tsr'],
				'',
				['--tsa-server-url' => 'https://tsa.example.test/tsr'],
				[],
			],
			'url with policy OID and no authentication' => [
				['url' => 'https://tsa.example.test/tsr', 'policy_oid' => '1.2.3.4', 'auth_type' => 'none'],
				'tsa secret',
				['--tsa-server-url' => 'https://tsa.example.test/tsr', '--tsa-policy-oid' => '1.2.3.4'],
				[],
			],
			'basic authentication: user on the command line, password over stdin' => [
				$tsa,
				'tsa secret',
				[
					'--tsa-server-url' => 'https://tsa.example.test/tsr',
					'--tsa-policy-oid' => '1.2.3.4',
					'--tsa-authentication' => 'PASSWORD',
					'--tsa-user' => 'alice',
				],
				['-tsp' => 'tsa secret'],
			],
			'basic authentication with shell characters in the password' => [
				$tsa,
				"p4\$s 'w\"ord",
				[
					'--tsa-server-url' => 'https://tsa.example.test/tsr',
					'--tsa-policy-oid' => '1.2.3.4',
					'--tsa-authentication' => 'PASSWORD',
					'--tsa-user' => 'alice',
				],
				['-tsp' => "p4\$s 'w\"ord"],
			],
			'basic authentication without a stored password is skipped' => [
				$tsa,
				'',
				['--tsa-server-url' => 'https://tsa.example.test/tsr', '--tsa-policy-oid' => '1.2.3.4'],
				[],
			],
			'basic authentication without a URL sends nothing' => [
				['url' => '', 'auth_type' => 'basic', 'username' => 'alice'],
				'tsa secret',
				[],
				[],
			],
			'basic authentication without a username is skipped' => [
				['url' => 'https://tsa.example.test/tsr', 'auth_type' => 'basic', 'username' => ''],
				'tsa secret',
				['--tsa-server-url' => 'https://tsa.example.test/tsr'],
				[],
			],
		];
	}

	#[DataProvider('providerCertificationLevelWithoutVisibleElements')]
	public function testCertificationLevelWithoutVisibleElements(bool $docMdpEnabled, array $visibleElements, string $pdfContent, array $tsaSettings, array $expectedParameters): void {
		if (self::$certificateEngineFactory === null || empty(self::$certificateContent)) {
			$this->markTestSkipped('Certificate initialization failed');
		}
		$this->resolvedPolicyValues[TsaPolicy::KEY] = TsaPolicyValue::encode($tsaSettings);
		$this->appConfig->setValueString('libresign', 'java_path', __FILE__);
		$this->appConfig->setValueString('libresign', 'jsignpdf_temp_path', sys_get_temp_dir());
		$this->appConfig->setValueString('libresign', 'jsignpdf_path', __DIR__);
		$this->persistSignatureStampPolicy('', SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY, 10, SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE, 100, 100, 'deleted');
		$this->persistHashAlgorithmPolicy('SHA256');
		$this->signatureBackgroundService->method('getSignatureBackgroundType')->willReturn('deleted');

		$inputFile = $this->createMock(\OC\Files\Node\File::class);
		$inputFile->method('getContent')->willReturn($pdfContent);

		$paramsSeen = [];
		$mock = $this->createMock(JSignPDF::class);
		$mock->method('setParam')
			->willReturnCallback(function (JSignParam $param) use (&$paramsSeen): void {
				$paramsSeen[] = $param->getJSignParameters();
			});
		$mock->method('sign')->willReturn('content');

		$docMdpConfigService = $this->createMock(DocMdpConfigService::class);
		$docMdpConfigService->method('isEnabled')->willReturn($docMdpEnabled);
		$docMdpConfigService->method('getLevel')->willReturn(DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS);

		$jSignPdfHandler = $this->getInstance();
		$this->setDocMdpConfigService($jSignPdfHandler, $docMdpConfigService);
		$jSignPdfHandler->setVisibleElements($visibleElements);
		$jSignPdfHandler->setJSignPdf($mock);
		$jSignPdfHandler->setInputFile($inputFile);
		$jSignPdfHandler->setSignatureParams(['SignerCommonName' => 'Test User']);
		$jSignPdfHandler->setCertificate(self::$certificateContent);
		$jSignPdfHandler->setPassword('password');
		$jSignPdfHandler->getSignedContent();

		$this->assertCount(1, $paramsSeen);
		$paramsAsOptions = preg_replace('/\\/\S+app-dark.png/', 'signature.png', $paramsSeen[0]);
		$this->assertSame(self::expectedJSignParameters($expectedParameters), $paramsAsOptions);
	}

	public static function providerCertificationLevelWithoutVisibleElements(): array {
		$element = self::getElement([
			'page' => 1,
			'llx' => 10,
			'lly' => 10,
			'urx' => 110,
			'ury' => 60,
		], realpath(__DIR__ . '/../../../../../img/app-dark.png'));
		$tsa = ['url' => 'https://tsa.example.test/tsr'];
		return [
			'certification before the TSA options when the PDF has no signature' => [
				true,
				[],
				'%PDF-1.6',
				$tsa,
				['-cl' => DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS->name, '--tsa-server-url' => 'https://tsa.example.test/tsr', '--hash-algorithm' => 'SHA256'],
			],
			'no certification when the PDF already has a signature' => [
				true,
				[],
				"%PDF-1.6\n/ByteRange [0 0 0 0]",
				$tsa,
				['--tsa-server-url' => 'https://tsa.example.test/tsr', '--hash-algorithm' => 'SHA256'],
			],
			'no certification when DocMDP is disabled, even with a visible element on a signed PDF' => [
				false,
				[$element],
				"%PDF-1.6\n/ByteRange [0 0 0 0]",
				['url' => ''],
				['--hash-algorithm' => 'SHA256', '--l2-text' => '', '-V', '-llx' => '10', '-lly' => '10', '-urx' => '110', '-ury' => '60', '--bg-path' => 'signature.png'],
			],
		];
	}

	#[DataProvider('providerToJSignParameters')]
	public function testToJSignParameters(array $params, array $expected): void {
		$jSignPdfHandler = $this->getInstance();

		$this->assertSame($expected, self::invokePrivate($jSignPdfHandler, 'toJSignParameters', [$params]));
	}

	public static function providerToJSignParameters(): array {
		return [
			'null is a flag' => [['-V' => null], ['-V']],
			'integers become strings' => [['-pg' => 2, '-llx' => 0], ['-pg' => '2', '-llx' => '0']],
			'floats become strings' => [['--font-size' => 16.5, '--bg-scale' => 1.0], ['--font-size' => '16.5', '--bg-scale' => '1']],
			'empty string is a value' => [['--l2-text' => ''], ['--l2-text' => '']],
			'text is kept as is' => [['--l2-text' => 'a"b $c \'d'], ['--l2-text' => 'a"b $c \'d']],
			'order is preserved' => [['-a' => null, '-kst' => 'PKCS12', '-cl' => 'CERTIFIED_NO_CHANGES_ALLOWED'], ['-a', '-kst' => 'PKCS12', '-cl' => 'CERTIFIED_NO_CHANGES_ALLOWED']],
		];
	}
}
