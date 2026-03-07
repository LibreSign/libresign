<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\SetupCheck;

use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Handler\CertificateEngine\IEngineHandler;
use OCA\Libresign\Helper\ConfigureCheckHelper;
use OCA\Libresign\SetupCheck\CertificateEngineSetupCheck;
use OCP\IL10N;
use OCP\SetupCheck\SetupResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

interface MockableIEngineHandler extends IEngineHandler
{
  public function getName(): string;
}

final class CertificateEngineSetupCheckTest extends TestCase
{
  /** @var IL10N&MockObject */
  private $l10n;
  /** @var CertificateEngineFactory&MockObject */
  private $certificateEngineFactory;

  protected function setUp(): void
  {
    parent::setUp();
    $this->l10n = $this->createMock(IL10N::class);
    $this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
  }

  private function getInstance(): CertificateEngineSetupCheck
  {
    return new CertificateEngineSetupCheck(
      $this->l10n,
      $this->certificateEngineFactory
    );
  }

  public function testGetName(): void
  {
    $this->l10n->expects($this->once())
      ->method('t')
      ->with('Certificate engine')
      ->willReturn('Certificate engine');

    $check = $this->getInstance();
    $this->assertSame('Certificate engine', $check->getName());
  }

  public function testGetCategory(): void
  {
    $check = $this->getInstance();
    $this->assertSame('security', $check->getCategory());
  }

  public function testRunWithException(): void
  {
    $engineMock = $this->createMock(MockableIEngineHandler::class);
    $engineMock->method('getName')->willReturn('cfssl');

    $this->certificateEngineFactory->expects($this->exactly(2))
      ->method('getEngine')
      ->willReturnOnConsecutiveCalls(
        $this->throwException(new \RuntimeException('Engine not found')),
        $engineMock
      );

    $calls = 0;
    $this->l10n->expects($this->exactly(2))
      ->method('t')
      ->willReturnCallback(function ($arg1, $arg2 = []) use (&$calls) {
        $calls++;
        if ($calls === 1) {
          $this->assertEquals('Define the certificate engine to use', $arg1);
          $this->assertEquals([], $arg2);
          return 'Define the certificate engine to use';
        } elseif ($calls === 2) {
          $this->assertEquals('Run occ libresign:configure:%s --help', $arg1);
          $this->assertEquals(['cfssl'], $arg2);
          return 'Run occ libresign:configure:cfssl --help';
        }
      });

    $check = $this->getInstance();
    $result = $check->run();

    $this->assertInstanceOf(SetupResult::class, $result);
    $this->assertEquals('error', $result->getSeverity());
    $this->assertEquals('Define the certificate engine to use', $result->getDescription());
    $this->assertEquals('Run occ libresign:configure:cfssl --help', $result->getLinkToDoc());
  }

  public function testRunWithSuccess(): void
  {
    $engine = $this->createMock(IEngineHandler::class);
    $engine->method('configureCheck')
      ->willReturn([
        (new ConfigureCheckHelper())->setSuccessMessage('Engine OK'),
      ]);

    $this->certificateEngineFactory->method('getEngine')->willReturn($engine);

    $check = $this->getInstance();
    $result = $check->run();

    $this->assertInstanceOf(SetupResult::class, $result);
    $this->assertEquals('success', $result->getSeverity());
    $this->assertEquals('Engine OK', $result->getDescription());
  }

  public function testRunWithError(): void
  {
    $engine = $this->createMock(IEngineHandler::class);
    $engine->method('configureCheck')
      ->willReturn([
        (new ConfigureCheckHelper())->setErrorMessage('Engine error'),
      ]);

    $this->certificateEngineFactory->method('getEngine')->willReturn($engine);

    $check = $this->getInstance();
    $result = $check->run();

    $this->assertInstanceOf(SetupResult::class, $result);
    $this->assertEquals('error', $result->getSeverity());
    $this->assertEquals('[ERROR] Engine error', $result->getDescription());
  }

  public function testRunWithMixedResults(): void
  {
    $engine = $this->createMock(IEngineHandler::class);
    $engine->method('configureCheck')
      ->willReturn([
        (new ConfigureCheckHelper())->setSuccessMessage('Success'),
        (new ConfigureCheckHelper())->setInfoMessage('Info message'),
        (new ConfigureCheckHelper())->setErrorMessage('Error'),
      ]);

    $this->certificateEngineFactory->method('getEngine')->willReturn($engine);

    $check = $this->getInstance();
    $result = $check->run();

    $this->assertInstanceOf(SetupResult::class, $result);
    $this->assertEquals('error', $result->getSeverity());
    $expected = "Success\nInfo message\n[ERROR] Error";
    $this->assertEquals($expected, $result->getDescription());
  }

  public function testRunWithEmptyResults(): void
  {
    $engine = $this->createMock(IEngineHandler::class);
    $engine->method('configureCheck')->willReturn([]);

    $this->certificateEngineFactory->method('getEngine')->willReturn($engine);

    $this->l10n->method('t')
      ->with('Certificate engine is configured correctly')
      ->willReturn('Certificate engine is configured correctly');

    $check = $this->getInstance();
    $result = $check->run();

    $this->assertInstanceOf(SetupResult::class, $result);
    $this->assertEquals('success', $result->getSeverity());
    $this->assertEquals('Certificate engine is configured correctly', $result->getDescription());
  }

  public function testRunWithOnlyWarnings(): void
  {
    $engine = $this->createMock(IEngineHandler::class);

    $warning1 = new ConfigureCheckHelper();
    $warning1->setStatus('warning');
    $warning1->setMessage('Disk space low');

    $warning2 = new ConfigureCheckHelper();
    $warning2->setStatus('warning');
    $warning2->setMessage('Certificate expires soon');

    $engine->method('configureCheck')
      ->willReturn([$warning1, $warning2]);

    $this->certificateEngineFactory->method('getEngine')->willReturn($engine);

    $check = $this->getInstance();
    $result = $check->run();

    $this->assertInstanceOf(SetupResult::class, $result);
    $this->assertEquals('warning', $result->getSeverity());
    $expected = "[WARNING] Disk space low\n[WARNING] Certificate expires soon";
    $this->assertEquals($expected, $result->getDescription());
  }

  public function testRunCollectsTipsAndRemovesDuplicates(): void
  {
    $engine = $this->createMock(IEngineHandler::class);

    $helper1 = (new ConfigureCheckHelper())
      ->setErrorMessage('First error')
      ->setTip('Tip A');
    $helper2 = (new ConfigureCheckHelper())
      ->setInfoMessage('Irrelevant info')
      ->setTip('');
    $helper3 = (new ConfigureCheckHelper())
      ->setErrorMessage('Second error')
      ->setTip('Tip B');
    $helper4 = (new ConfigureCheckHelper())
      ->setErrorMessage('First error again')
      ->setTip('Tip A');

    $engine->method('configureCheck')
      ->willReturn([$helper1, $helper2, $helper3, $helper4]);

    $this->certificateEngineFactory->method('getEngine')->willReturn($engine);

    $check = $this->getInstance();
    $result = $check->run();

    $this->assertInstanceOf(SetupResult::class, $result);
    $this->assertEquals('error', $result->getSeverity());

    $expectedDesc = "[ERROR] First error\nIrrelevant info\n[ERROR] Second error\n[ERROR] First error again";
    $this->assertEquals($expectedDesc, $result->getDescription());
    $this->assertEquals("Tip A\nTip B", $result->getLinkToDoc());
  }
}
