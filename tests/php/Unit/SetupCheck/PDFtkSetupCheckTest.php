<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\SetupCheck;

use OCA\Libresign\Helper\JavaHelper;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\SignSetupService;
use OCA\Libresign\SetupCheck\PDFtkSetupCheck;
use OCA\Libresign\Tests\Unit\SetupCheck\Mock\ExecMock;
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

class PDFtkSetupCheckTest extends TestCase {
	/** @var IL10N|MockObject */
	private $l10n;
	/** @var IAppConfig|MockObject */
	private $appConfig;
	/** @var SignSetupService|MockObject */
	private $signSetupService;
	/** @var IURLGenerator|MockObject */
	private $urlGenerator;
	/** @var IAppManager|MockObject */
	private $appManager;
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var IConfig|MockObject */
	private $systemConfig;
	/** @var JavaHelper|MockObject */
	private $javaHelper;

	protected function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->signSetupService = $this->createMock(SignSetupService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->systemConfig = $this->createMock(IConfig::class);
		$this->javaHelper = $this->createMock(JavaHelper::class);

		$this->l10n->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});

		FileSystemMock::$files = [];
		ExecMock::$commands = [];
	}

	protected function tearDown(): void {
		parent::tearDown();
		FileSystemMock::$files = [];
		ExecMock::$commands = [];
	}

	private function getInstance(): PDFtkSetupCheck {
		return new PDFtkSetupCheck(
			$this->l10n,
			$this->appConfig,
			$this->signSetupService,
			$this->urlGenerator,
			$this->appManager,
			$this->logger,
			$this->systemConfig,
			$this->javaHelper
		);
	}

	public function testGetName(): void {
		$this->l10n->expects($this->once())
			->method('t')
			->with('PDFtk')
			->willReturn('PDFtk');
		$check = $this->getInstance();
		$this->assertEquals('PDFtk', $check->getName());
	}

	public function testGetCategory(): void {
		$check = $this->getInstance();
		$this->assertEquals('system', $check->getCategory());
	}

	public function testRunNoPdftkPath(): void {
		$this->appConfig->method('getValueString')
			->with('libresign', 'pdftk_path')
			->willReturn('');

		$check = $this->getInstance();
		$result = $check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::ERROR, $result->getSeverity());
		$this->assertStringContainsString('PDFtk not found', $result->getDescription());
	}

	public function testRunWithVerifyError(): void {
		$pdftkPath = '/fake/pdftk.jar';
		$this->appConfig->method('getValueString')
			->with('libresign', 'pdftk_path')
			->willReturn($pdftkPath);

		$debugEnabled = false;
		$this->systemConfig->method('getSystemValueBool')
			->with('debug', false)
			->willReturn($debugEnabled);

		$verifyResult = ['SIGNATURE_DATA_NOT_FOUND' => true];

		$this->signSetupService->expects($this->once())
			->method('willUseLocalCert')
			->with($debugEnabled);
		$this->signSetupService->expects($this->once())
			->method('verify')
			->with(php_uname('m'), 'pdftk')
			->willReturn($verifyResult);

		$check = $this->getInstance();
		$result = $check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::ERROR, $result->getSeverity());
		$this->assertStringContainsString('Signature data not found', $result->getDescription());
	}

	public function testRunFileNotExists(): void {
		$pdftkPath = '/fake/pdftk.jar';
		$this->appConfig->method('getValueString')
			->with('libresign', 'pdftk_path')
			->willReturn($pdftkPath);

		$this->systemConfig->method('getSystemValueBool')
			->with('debug', false)
			->willReturn(false);

		$this->signSetupService->method('willUseLocalCert');
		$this->signSetupService->method('verify')
			->willReturn([]);

		FileSystemMock::$files[$pdftkPath] = false;

		$check = $this->getInstance();
		$result = $check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::ERROR, $result->getSeverity());
		$this->assertStringContainsString('PDFtk binary not found', $result->getDescription());
	}

	public function testRunJavaMissing(): void {
		$pdftkPath = '/fake/pdftk.jar';
		$this->appConfig->method('getValueString')
			->with('libresign', 'pdftk_path')
			->willReturn($pdftkPath);

		$this->systemConfig->method('getSystemValueBool')
			->with('debug', false)
			->willReturn(false);

		$this->signSetupService->method('willUseLocalCert');
		$this->signSetupService->method('verify')->willReturn([]);

		FileSystemMock::$files[$pdftkPath] = true;

		$this->javaHelper->method('getJavaPath')->willReturn('');

		$check = $this->getInstance();
		$result = $check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::ERROR, $result->getSeverity());
		$this->assertStringContainsString('Necessary Java to run PDFtk', $result->getDescription());
	}

	public function testRunJavaPathExistsButFileNotExists(): void {
		$pdftkPath = '/fake/pdftk.jar';
		$javaPath = '/fake/java';
		$this->appConfig->method('getValueString')
			->with('libresign', 'pdftk_path')
			->willReturn($pdftkPath);

		$this->systemConfig->method('getSystemValueBool')
			->with('debug', false)
			->willReturn(false);

		$this->signSetupService->method('willUseLocalCert');
		$this->signSetupService->method('verify')->willReturn([]);

		FileSystemMock::$files[$pdftkPath] = true;

		$this->javaHelper->method('getJavaPath')->willReturn($javaPath);
		FileSystemMock::$files[$javaPath] = false;

		$check = $this->getInstance();
		$result = $check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::ERROR, $result->getSeverity());
		$this->assertStringContainsString('Necessary Java to run PDFtk', $result->getDescription());
	}

	public function testRunExecFailure(): void {
		$pdftkPath = '/fake/pdftk.jar';
		$javaPath = '/fake/java';
		$this->appConfig->method('getValueString')
			->with('libresign', 'pdftk_path')
			->willReturn($pdftkPath);

		$this->systemConfig->method('getSystemValueBool')
			->with('debug', false)
			->willReturn(false);

		$this->signSetupService->method('willUseLocalCert');
		$this->signSetupService->method('verify')->willReturn([]);

		FileSystemMock::$files[$pdftkPath] = true;
		FileSystemMock::$files[$javaPath] = true;

		$this->javaHelper->method('getJavaPath')->willReturn($javaPath);

		$expectedCommand = $javaPath . ' -jar ' . $pdftkPath . ' --version 2>&1';
		ExecMock::$commands[$expectedCommand] = [
			'output' => [],
			'result_code' => 1,
		];

		$check = $this->getInstance();
		$result = $check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::ERROR, $result->getSeverity());
		$this->assertStringContainsString('Failure to check PDFtk version', $result->getDescription());
	}

	public function testRunInvalidBinary(): void {
		$pdftkPath = '/fake/pdftk.jar';
		$javaPath = '/fake/java';
		$this->appConfig->method('getValueString')
			->with('libresign', 'pdftk_path')
			->willReturn($pdftkPath);

		$this->systemConfig->method('getSystemValueBool')
			->with('debug', false)
			->willReturn(false);

		$this->signSetupService->method('willUseLocalCert');
		$this->signSetupService->method('verify')->willReturn([]);

		FileSystemMock::$files[$pdftkPath] = true;
		FileSystemMock::$files[$javaPath] = true;

		$this->javaHelper->method('getJavaPath')->willReturn($javaPath);

		$expectedCommand = $javaPath . ' -jar ' . $pdftkPath . ' --version 2>&1';
		ExecMock::$commands[$expectedCommand] = [
			'output' => ['unexpected output'],
			'result_code' => 0,
		];

		$check = $this->getInstance();
		$result = $check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::ERROR, $result->getSeverity());
		$this->assertStringContainsString('PDFtk binary is invalid', $result->getDescription());
	}

	public function testRunVersionMismatch(): void {
		$pdftkPath = '/fake/pdftk.jar';
		$javaPath = '/fake/java';
		$wrongVersion = '2.0.0';
		$this->appConfig->method('getValueString')
			->with('libresign', 'pdftk_path')
			->willReturn($pdftkPath);

		$this->systemConfig->method('getSystemValueBool')
			->with('debug', false)
			->willReturn(false);

		$this->signSetupService->method('willUseLocalCert');
		$this->signSetupService->method('verify')->willReturn([]);

		FileSystemMock::$files[$pdftkPath] = true;
		FileSystemMock::$files[$javaPath] = true;

		$this->javaHelper->method('getJavaPath')->willReturn($javaPath);

		$expectedCommand = $javaPath . ' -jar ' . $pdftkPath . ' --version 2>&1';
		ExecMock::$commands[$expectedCommand] = [
			'output' => ["pdftk port to java $wrongVersion a Handy Tool"],
			'result_code' => 0,
		];

		$check = $this->getInstance();
		$result = $check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::ERROR, $result->getSeverity());
		$this->assertStringContainsString('Necessary install the version', $result->getDescription());
	}

	public function testRunSuccess(): void {
		$pdftkPath = '/fake/pdftk.jar';
		$javaPath = '/fake/java';
		$expectedVersion = InstallService::PDFTK_VERSION;

		$this->appConfig->method('getValueString')
			->with('libresign', 'pdftk_path')
			->willReturn($pdftkPath);

		$this->systemConfig->method('getSystemValueBool')
			->with('debug', false)
			->willReturn(false);

		$this->signSetupService->method('willUseLocalCert');
		$this->signSetupService->method('verify')->willReturn([]);

		FileSystemMock::$files[$pdftkPath] = true;
		FileSystemMock::$files[$javaPath] = true;

		$this->javaHelper->method('getJavaPath')->willReturn($javaPath);

		$expectedCommand = $javaPath . ' -jar ' . $pdftkPath . ' --version 2>&1';
		ExecMock::$commands[$expectedCommand] = [
			'output' => ["pdftk port to java $expectedVersion a Handy Tool"],
			'result_code' => 0,
		];

		$check = $this->getInstance();
		$result = $check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::SUCCESS, $result->getSeverity());
		$this->assertStringContainsString("PDFtk version: $expectedVersion", $result->getDescription());
		$this->assertStringContainsString("PDFtk path: $pdftkPath", $result->getDescription());
	}

	public function testRunWithDebugIgnoresSignatureError(): void {
		$pdftkPath = '/fake/pdftk.jar';
		$javaPath = '/fake/java';
		$expectedVersion = InstallService::PDFTK_VERSION;

		$this->appConfig->method('getValueString')
			->with('libresign', 'pdftk_path')
			->willReturn($pdftkPath);

		$this->systemConfig->method('getSystemValueBool')
			->with('debug', false)
			->willReturn(true);

		$verifyResult = ['SIGNATURE_DATA_NOT_FOUND' => true];
		$this->signSetupService->method('willUseLocalCert')->with(true);
		$this->signSetupService->method('verify')
			->with(php_uname('m'), 'pdftk')
			->willReturn($verifyResult);

		FileSystemMock::$files[$pdftkPath] = true;
		FileSystemMock::$files[$javaPath] = true;
		$this->javaHelper->method('getJavaPath')->willReturn($javaPath);

		$expectedCommand = $javaPath . ' -jar ' . $pdftkPath . ' --version 2>&1';
		ExecMock::$commands[$expectedCommand] = [
			'output' => ["pdftk port to java $expectedVersion a Handy Tool"],
			'result_code' => 0,
		];

		$check = $this->getInstance();
		$result = $check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::SUCCESS, $result->getSeverity());
	}

	public function testRunEmptyCommandOutput(): void {
		$pdftkPath = '/fake/pdftk.jar';
		$javaPath = '/fake/java';

		$this->appConfig->method('getValueString')
			->with('libresign', 'pdftk_path')
			->willReturn($pdftkPath);

		$this->systemConfig->method('getSystemValueBool')
			->with('debug', false)
			->willReturn(false);

		$this->signSetupService->method('willUseLocalCert');
		$this->signSetupService->method('verify')->willReturn([]);

		FileSystemMock::$files[$pdftkPath] = true;
		FileSystemMock::$files[$javaPath] = true;
		$this->javaHelper->method('getJavaPath')->willReturn($javaPath);

		$expectedCommand = $javaPath . ' -jar ' . $pdftkPath . ' --version 2>&1';
		ExecMock::$commands[$expectedCommand] = [
			'output' => [],
			'result_code' => 0,
		];

		$check = $this->getInstance();
		$result = $check->run();

		$this->assertInstanceOf(SetupResult::class, $result);
		$this->assertEquals(SetupResult::ERROR, $result->getSeverity());
		$this->assertStringContainsString('PDFtk binary is invalid', $result->getDescription());
	}
}
