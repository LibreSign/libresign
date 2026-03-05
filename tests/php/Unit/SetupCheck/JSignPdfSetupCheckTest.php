<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\SetupCheck;

use OCA\Libresign\Handler\SignEngine\JSignPdfHandler;
use OCA\Libresign\Helper\JavaHelper;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\SignSetupService;
use OCA\Libresign\SetupCheck\JSignPdfSetupCheck;
use OCA\Libresign\Tests\Unit\SetupCheck\Mock\FileSystemMock;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\SetupCheck\SetupResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class JSignPdfSetupCheckTest extends TestCase {
	/** @var IL10N&MockObject */
	private $l10n;
	/** @var IAppConfig&MockObject */
	private $appConfig;
	/** @var JSignPdfHandler&MockObject */
	private $jSignPdfHandler;
	/** @var SignSetupService&MockObject */
	private $signSetupService;
	/** @var IURLGenerator&MockObject */
	private $urlGenerator;
	/** @var IAppManager&MockObject */
	private $appManager;
	/** @var LoggerInterface&MockObject */
	private $logger;
	/** @var IConfig&MockObject */
	private $systemConfig;
	/** @var JavaHelper&MockObject */
	private $javaHelper;

	/** @var JSignPdfSetupCheck */
	private $check;

	protected function setUp(): void {
		parent::setUp();
		FileSystemMock::$files = [];

		$this->l10n = $this->createMock(IL10N::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->jSignPdfHandler = $this->createMock(JSignPdfHandler::class);
		$this->signSetupService = $this->createMock(SignSetupService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->systemConfig = $this->createMock(IConfig::class);
		$this->javaHelper = $this->createMock(JavaHelper::class);

		$this->check = new JSignPdfSetupCheck(
			$this->l10n,
			$this->appConfig,
			$this->jSignPdfHandler,
			$this->signSetupService,
			$this->urlGenerator,
			$this->appManager,
			$this->logger,
			$this->systemConfig,
			$this->javaHelper
		);
	}

	protected function tearDown(): void {
		FileSystemMock::$files = [];
		parent::tearDown();
	}

	private function mockTranslation(): void {
		$this->l10n->method('t')
			->willReturnCallback(function ($text, $params = []) {
				return vsprintf($text, $params);
			});
	}

	public function testGetName(): void {
		$this->l10n->expects($this->once())
			->method('t')
			->with('JSignPdf')
			->willReturn('JSignPdf');
		$this->assertSame('JSignPdf', $this->check->getName());
	}

	public function testGetCategory(): void {
		$this->assertSame('system', $this->check->getCategory());
	}

	public function testRunNoPathConfigured(): void {
		$this->mockTranslation();
		$this->appConfig->method('getValueString')
			->with('libresign', 'jsignpdf_jar_path')
			->willReturn('');

		$result = $this->check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertSame('error', $result->getSeverity());
		$this->assertStringContainsString('JSignPdf not found', $result->getDescription());
	}

	public function testRunVerifyFails(): void {
		$this->mockTranslation();
		$this->appConfig->method('getValueString')
			->willReturn('/fake/path/jsignpdf.jar');
		$this->systemConfig->method('getSystemValueBool')
			->with('debug', false)
			->willReturn(false);

		$this->signSetupService->expects($this->once())
			->method('willUseLocalCert')
			->with(false);
		$this->signSetupService->expects($this->once())
			->method('verify')
			->with($this->anything(), 'jsignpdf')
			->willReturn(['SOME_ERROR' => 'details']);

		$this->logger->expects($this->once())
			->method('error')
			->with('Invalid hash of binaries files', $this->anything());

		$result = $this->check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertSame('error', $result->getSeverity());
		$this->assertStringContainsString('Invalid hash', $result->getDescription());
	}

	public function testRunBinaryNotFound(): void {
		$this->mockTranslation();
		$jarPath = '/fake/path/jsignpdf.jar';
		$this->appConfig->method('getValueString')
			->willReturn($jarPath);
		$this->systemConfig->method('getSystemValueBool')
			->willReturn(false);

		$this->signSetupService->method('willUseLocalCert');
		$this->signSetupService->method('verify')
			->willReturn([]);

		FileSystemMock::$files[$jarPath] = false;

		$result = $this->check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertSame('error', $result->getSeverity());
		$this->assertStringContainsString('JSignPdf binary not found', $result->getDescription());
	}

	public function testRunJavaNotFound(): void {
		$this->mockTranslation();
		$jarPath = '/fake/path/jsignpdf.jar';
		$this->appConfig->method('getValueString')
			->willReturn($jarPath);
		$this->systemConfig->method('getSystemValueBool')
			->willReturn(false);
		$this->signSetupService->method('verify')
			->willReturn([]);

		FileSystemMock::$files[$jarPath] = true;

		$this->javaHelper->method('getJavaPath')
			->willReturn('');

		$result = $this->check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertSame('error', $result->getSeverity());
		$this->assertStringContainsString('Necessary Java to run JSignPdf', $result->getDescription());
	}

	private function createJSignParamMock() {
		return $this->getMockBuilder(\OCA\Libresign\Vendor\Jeidison\JSignPDF\Sign\JSignParam::class)
			->disableOriginalConstructor()
			->getMock();
	}

	public function testRunVersionEmpty(): void {
		$this->mockTranslation();
		$jarPath = '/fake/path/jsignpdf.jar';
		$this->appConfig->method('getValueString')
			->willReturn($jarPath);
		$this->systemConfig->method('getSystemValueBool')
			->willReturn(false);
		$this->signSetupService->method('verify')->willReturn([]);
		$this->javaHelper->method('getJavaPath')->willReturn('/usr/bin/java');
		FileSystemMock::$files[$jarPath] = true;
		FileSystemMock::$files['/usr/bin/java'] = true;

		$jsignPdfMock = $this->getMockBuilder(\OCA\Libresign\Vendor\Jeidison\JSignPDF\JSignPDF::class)
			->disableOriginalConstructor()
			->onlyMethods(['setParam', 'getVersion'])
			->getMock();
		$jsignPdfMock->method('getVersion')->willReturn('');

		$jsignParamMock = $this->createJSignParamMock();

		$this->jSignPdfHandler->method('getJSignPdf')->willReturn($jsignPdfMock);
		$this->jSignPdfHandler->method('getJSignParam')->willReturn($jsignParamMock);

		$result = $this->check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertSame('error', $result->getSeverity());
		$this->assertStringContainsString('Necessary install the version', $result->getDescription());
	}

	public function testRunVersionTooLow(): void {
		$this->mockTranslation();
		$jarPath = '/fake/path/jsignpdf.jar';
		$this->appConfig->method('getValueString')
			->willReturn($jarPath);
		$this->systemConfig->method('getSystemValueBool')
			->willReturn(false);
		$this->signSetupService->method('verify')->willReturn([]);
		$this->javaHelper->method('getJavaPath')->willReturn('/usr/bin/java');
		FileSystemMock::$files[$jarPath] = true;
		FileSystemMock::$files['/usr/bin/java'] = true;

		$jsignPdfMock = $this->getMockBuilder(\OCA\Libresign\Vendor\Jeidison\JSignPDF\JSignPDF::class)
			->disableOriginalConstructor()
			->onlyMethods(['setParam', 'getVersion'])
			->getMock();
		$jsignPdfMock->method('getVersion')->willReturn('1.0.0');

		$jsignParamMock = $this->createJSignParamMock();

		$this->jSignPdfHandler->method('getJSignPdf')->willReturn($jsignPdfMock);
		$this->jSignPdfHandler->method('getJSignParam')->willReturn($jsignParamMock);

		$result = $this->check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertSame('error', $result->getSeverity());
		$this->assertStringContainsString('bump JSignPdf version', $result->getDescription());
	}

	public function testRunVersionTooHigh(): void {
		$this->mockTranslation();
		$jarPath = '/fake/path/jsignpdf.jar';
		$this->appConfig->method('getValueString')
			->willReturn($jarPath);
		$this->systemConfig->method('getSystemValueBool')
			->willReturn(false);
		$this->signSetupService->method('verify')->willReturn([]);
		$this->javaHelper->method('getJavaPath')->willReturn('/usr/bin/java');
		FileSystemMock::$files[$jarPath] = true;
		FileSystemMock::$files['/usr/bin/java'] = true;

		$jsignPdfMock = $this->getMockBuilder(\OCA\Libresign\Vendor\Jeidison\JSignPDF\JSignPDF::class)
			->disableOriginalConstructor()
			->onlyMethods(['setParam', 'getVersion'])
			->getMock();
		$jsignPdfMock->method('getVersion')->willReturn('10.0.0');

		$jsignParamMock = $this->createJSignParamMock();

		$this->jSignPdfHandler->method('getJSignPdf')->willReturn($jsignPdfMock);
		$this->jSignPdfHandler->method('getJSignParam')->willReturn($jsignParamMock);

		$result = $this->check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertSame('error', $result->getSeverity());
		$this->assertStringContainsString('downgrade JSignPdf version', $result->getDescription());
	}

	public function testRunSuccess(): void {
		$this->mockTranslation();
		$jarPath = '/fake/path/jsignpdf.jar';
		$this->appConfig->method('getValueString')
			->willReturn($jarPath);
		$this->systemConfig->method('getSystemValueBool')
			->willReturn(false);
		$this->signSetupService->method('verify')->willReturn([]);
		$this->javaHelper->method('getJavaPath')->willReturn('/usr/bin/java');
		FileSystemMock::$files[$jarPath] = true;
		FileSystemMock::$files['/usr/bin/java'] = true;

		$jsignPdfMock = $this->getMockBuilder(\OCA\Libresign\Vendor\Jeidison\JSignPDF\JSignPDF::class)
			->disableOriginalConstructor()
			->onlyMethods(['setParam', 'getVersion'])
			->getMock();
		$jsignPdfMock->method('getVersion')->willReturn(InstallService::JSIGNPDF_VERSION);

		$jsignParamMock = $this->createJSignParamMock();

		$this->jSignPdfHandler->method('getJSignPdf')->willReturn($jsignPdfMock);
		$this->jSignPdfHandler->method('getJSignParam')->willReturn($jsignParamMock);

		$result = $this->check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertSame('success', $result->getSeverity());
		$this->assertStringContainsString('JSignPdf version: ' . InstallService::JSIGNPDF_VERSION, $result->getDescription());
		$this->assertStringContainsString('JSignPdf path: ' . $jarPath, $result->getDescription());
	}
}
