<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\SetupCheck;

use OCA\Libresign\Helper\JavaHelper;
use OCA\Libresign\Service\Install\SignSetupService;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\SetupCheck\JavaSetupCheck;
use OCA\Libresign\SetupCheck\FileSystemMock;
use OCA\Libresign\SetupCheck\ExecMock;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\App\IAppManager;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class JavaSetupCheckTest extends TestCase
{
  private IL10N&MockObject $l10n;
  private JavaHelper&MockObject $javaHelper;
  private SignSetupService&MockObject $signSetupService;
  private IURLGenerator&MockObject $urlGenerator;
  private IAppManager&MockObject $appManager;
  private LoggerInterface&MockObject $logger;
  private IConfig&MockObject $systemConfig;

  public function setUp(): void
  {
    parent::setUp();
    FileSystemMock::$files = [];
    ExecMock::$commands = [];

    $this->l10n = $this->createMock(IL10N::class);
    $this->javaHelper = $this->createMock(JavaHelper::class);
    $this->signSetupService = $this->createMock(SignSetupService::class);
    $this->urlGenerator = $this->createMock(IURLGenerator::class);
    $this->appManager = $this->createMock(IAppManager::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->systemConfig = $this->createMock(IConfig::class);
  }

  public function tearDown(): void
  {
    FileSystemMock::$files = [];
    ExecMock::$commands = [];
    parent::tearDown();
  }

  private function getInstance(): JavaSetupCheck
  {
    return new JavaSetupCheck(
      $this->l10n,
      $this->javaHelper,
      $this->signSetupService,
      $this->urlGenerator,
      $this->appManager,
      $this->logger,
      $this->systemConfig
    );
  }

  public function testJavaNotInstalled(): void
  {
    $this->javaHelper->method('getJavaPath')->willReturn('');
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(false);

    $this->l10n->expects($this->any())
      ->method('t')
      ->willReturnCallback(fn($string) => $string);

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('error', $result->getSeverity());
    $this->assertEquals('Java not installed', $result->getDescription());
  }

  public function testJavaBinaryNotFound(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(false);
    FileSystemMock::$files[$javaPath] = false;

    $this->l10n->expects($this->any())
      ->method('t')
      ->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('error', $result->getSeverity());
    $this->assertStringContainsString('Java binary not found', $result->getDescription());
  }

  public function testJavaVersionInvalid(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(false);
    FileSystemMock::$files[$javaPath] = true;
    ExecMock::$commands[$javaPath . ' -version 2>&1'] = [
      'output' => ['openjdk version "11.0.18"'],
      'result_code' => 0
    ];
    ExecMock::$commands[$javaPath . ' -XshowSettings:properties -version 2>&1'] = [
      'output' => ['native.encoding = UTF-8'],
      'result_code' => 0
    ];

    $this->signSetupService->method('verify')->willReturn([]);
    $this->signSetupService->method('willUseLocalCert');

    $this->l10n->expects($this->any())
      ->method('t')
      ->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('error', $result->getSeverity());
    $this->assertStringContainsString('Invalid java version', $result->getDescription());
  }

  public function testJavaSuccess(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(false);
    FileSystemMock::$files[$javaPath] = true;
    ExecMock::$commands[$javaPath . ' -version 2>&1'] = [
      'output' => [InstallService::JAVA_VERSION],
      'result_code' => 0
    ];
    ExecMock::$commands[$javaPath . ' -XshowSettings:properties -version 2>&1'] = [
      'output' => ['native.encoding = UTF-8'],
      'result_code' => 0
    ];

    $this->signSetupService->method('verify')->willReturn([]);
    $this->signSetupService->method('willUseLocalCert');

    $this->l10n->expects($this->any())
      ->method('t')
      ->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('success', $result->getSeverity());
    $this->assertStringContainsString('Java version: ' . InstallService::JAVA_VERSION, $result->getDescription());
    $this->assertStringContainsString('Java binary: ' . $javaPath, $result->getDescription());
  }

  public function testJavaVerifyResourceIntegrityFails(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(false);
    FileSystemMock::$files[$javaPath] = true;

    $verifyResult = ['HASH_FILE_ERROR' => true];
    $this->signSetupService->method('verify')
      ->with($this->anything(), 'java')
      ->willReturn($verifyResult);
    $this->signSetupService->method('willUseLocalCert');

    $this->logger->expects($this->once())
      ->method('error')
      ->with('Invalid hash of binaries files', ['result' => $verifyResult]);

    $this->appManager->method('isEnabledForUser')->with('logreader')->willReturn(false);

    $this->l10n->expects($this->any())
      ->method('t')
      ->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('error', $result->getSeverity());
    $this->assertStringContainsString('Invalid hash of binaries files', $result->getDescription());
  }

  public function testJavaExecutionFails(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(false);
    FileSystemMock::$files[$javaPath] = true;

    $this->signSetupService->method('verify')->willReturn([]);
    $this->signSetupService->method('willUseLocalCert');

    ExecMock::$commands[$javaPath . ' -version 2>&1'] = [
      'output' => ['some error output'],
      'result_code' => 1
    ];

    $this->l10n->expects($this->any())
      ->method('t')
      ->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('error', $result->getSeverity());
    $this->assertEquals('Failure to check Java version.', $result->getDescription());
  }

  public function testJavaEmptyOutput(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(false);
    FileSystemMock::$files[$javaPath] = true;

    $this->signSetupService->method('verify')->willReturn([]);
    $this->signSetupService->method('willUseLocalCert');

    ExecMock::$commands[$javaPath . ' -version 2>&1'] = [
      'output' => [],
      'result_code' => 0
    ];

    $this->l10n->expects($this->any())
      ->method('t')
      ->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('error', $result->getSeverity());
    $this->assertStringContainsString('Failed to execute Java', $result->getDescription());
  }

  public function testJavaEncodingNotFound(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(false);
    FileSystemMock::$files[$javaPath] = true;

    $this->signSetupService->method('verify')->willReturn([]);
    $this->signSetupService->method('willUseLocalCert');

    ExecMock::$commands[$javaPath . ' -version 2>&1'] = [
      'output' => [InstallService::JAVA_VERSION],
      'result_code' => 0
    ];

    ExecMock::$commands[$javaPath . ' -XshowSettings:properties -version 2>&1'] = [
      'output' => ['alguma outra coisa'],
      'result_code' => 0
    ];

    $this->l10n->expects($this->any())
      ->method('t')
      ->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('error', $result->getSeverity());
    $this->assertStringContainsString('Java encoding not found', $result->getDescription());
  }

  public function testJavaEncodingNonUtf8(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(false);
    FileSystemMock::$files[$javaPath] = true;

    $this->signSetupService->method('verify')->willReturn([]);
    $this->signSetupService->method('willUseLocalCert');

    ExecMock::$commands[$javaPath . ' -version 2>&1'] = [
      'output' => [InstallService::JAVA_VERSION],
      'result_code' => 0
    ];

    ExecMock::$commands[$javaPath . ' -XshowSettings:properties -version 2>&1'] = [
      'output' => ['native.encoding = ISO-8859-1'],
      'result_code' => 0
    ];

    $this->l10n->expects($this->any())
      ->method('t')
      ->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('info', $result->getSeverity());
    $this->assertStringContainsString('Non-UTF-8 encoding detected', $result->getDescription());
    $this->assertStringContainsString('ISO-8859-1', $result->getDescription());
  }

  public function testGetName(): void
  {
    $this->l10n->method('t')->willReturnArgument(0);
    $instance = $this->getInstance();
    $this->assertEquals('Java', $instance->getName());
  }

  public function testGetCategory(): void
  {
    $instance = $this->getInstance();
    $this->assertEquals('system', $instance->getCategory());
  }

  public function testVerifyResourceIntegritySignatureDataNotFoundWithoutDebug(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(false);
    FileSystemMock::$files[$javaPath] = true;

    $verifyResult = ['SIGNATURE_DATA_NOT_FOUND' => true];
    $this->signSetupService->method('verify')
      ->with(php_uname('m'), 'java')
      ->willReturn($verifyResult);

    $this->signSetupService->method('willUseLocalCert');

    $this->l10n->method('t')->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('error', $result->getSeverity());
    $this->assertStringContainsString('Signature data not found', $result->getDescription());
    $this->assertStringContainsString('running from source code', $result->getLinkToDoc());
  }

  public function testVerifyResourceIntegrityEmptySignatureDataWithoutDebug(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(false);
    FileSystemMock::$files[$javaPath] = true;

    $verifyResult = ['EMPTY_SIGNATURE_DATA' => true];
    $this->signSetupService->method('verify')
      ->with(php_uname('m'), 'java')
      ->willReturn($verifyResult);

    $this->signSetupService->method('willUseLocalCert');

    $this->l10n->method('t')->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('error', $result->getSeverity());
    $this->assertStringContainsString('Your signature data is empty', $result->getDescription());
  }

  public function testVerifyResourceIntegritySignatureDataNotFoundWithDebug(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(true);
    FileSystemMock::$files[$javaPath] = true;

    $verifyResult = ['SIGNATURE_DATA_NOT_FOUND' => true];
    $this->signSetupService->method('verify')
      ->with(php_uname('m'), 'java')
      ->willReturn($verifyResult);

    ExecMock::$commands[$javaPath . ' -version 2>&1'] = [
      'output' => [InstallService::JAVA_VERSION],
      'result_code' => 0
    ];
    ExecMock::$commands[$javaPath . ' -XshowSettings:properties -version 2>&1'] = [
      'output' => ['native.encoding = UTF-8'],
      'result_code' => 0
    ];

    $this->l10n->method('t')->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('success', $result->getSeverity());
  }

  public function testVerifyResourceIntegrityHashFileErrorWithDebug(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(true);
    FileSystemMock::$files[$javaPath] = true;

    $verifyResult = ['HASH_FILE_ERROR' => true];
    $this->signSetupService->method('verify')
      ->with(php_uname('m'), 'java')
      ->willReturn($verifyResult);

    $this->l10n->method('t')->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('error', $result->getSeverity());
    $this->assertStringContainsString('Invalid hash of binaries files', $result->getDescription());
    $this->assertStringContainsString('Debug mode is enabled', $result->getLinkToDoc());
  }

  public function testVerifyResourceIntegrityHashFileErrorWithoutDebugWithLogReader(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(false);
    FileSystemMock::$files[$javaPath] = true;

    $verifyResult = ['HASH_FILE_ERROR' => true];
    $this->signSetupService->method('verify')
      ->with(php_uname('m'), 'java')
      ->willReturn($verifyResult);

    $this->appManager->method('isEnabledForUser')->with('logreader')->willReturn(true);

    $this->urlGenerator->method('linkToRouteAbsolute')
      ->with('settings.adminsettings.form', ['section' => 'logging'])
      ->willReturn('https://example.com/settings/logging');

    $this->logger->expects($this->once())
      ->method('error')
      ->with('Invalid hash of binaries files', $this->anything());

    $this->l10n->method('t')->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('error', $result->getSeverity());
    $this->assertStringContainsString('Invalid hash of binaries files', $result->getDescription());
    $this->assertStringContainsString('Check your nextcloud.log file on', $result->getLinkToDoc());
  }

  public function testVerifyResourceIntegrityHashFileErrorWithoutDebugAndNoLogReader(): void
  {
    $javaPath = '/fake/java';
    $this->javaHelper->method('getJavaPath')->willReturn($javaPath);
    $this->systemConfig->method('getSystemValueBool')->with('debug', false)->willReturn(false);
    FileSystemMock::$files[$javaPath] = true;

    $verifyResult = ['HASH_FILE_ERROR' => true];
    $this->signSetupService->method('verify')
      ->with(php_uname('m'), 'java')
      ->willReturn($verifyResult);

    $this->appManager->method('isEnabledForUser')->with('logreader')->willReturn(false);

    $this->logger->expects($this->once())
      ->method('error')
      ->with('Invalid hash of binaries files', $this->anything());

    $this->l10n->method('t')->willReturnCallback(fn($string, $params = []) => vsprintf($string, $params));

    $instance = $this->getInstance();
    $result = $instance->run();

    $this->assertEquals('error', $result->getSeverity());
    $this->assertStringContainsString('Invalid hash of binaries files', $result->getDescription());
    $this->assertStringContainsString('Check your nextcloud.log file', $result->getLinkToDoc());
  }
}
