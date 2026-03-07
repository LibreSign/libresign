<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Mock extension_loaded in the target namespace to control its behavior in tests.
 */

namespace OCA\Libresign\SetupCheck {
  function extension_loaded(string $name): bool
  {
    return \OCA\Libresign\Tests\Unit\SetupCheck\ImagickSetupCheckTest::$mockExtensionLoaded[$name] ?? \extension_loaded($name);
  }
}

namespace OCA\Libresign\Tests\Unit\SetupCheck {

  use OCA\Libresign\SetupCheck\ImagickSetupCheck;
  use OCP\IL10N;
  use OCP\SetupCheck\SetupResult;
  use PHPUnit\Framework\MockObject\MockObject;
  use PHPUnit\Framework\TestCase;


  class ImagickSetupCheckTest extends TestCase
  {
    /** @var array<string, bool> */
    public static array $mockExtensionLoaded = [];

    /** @var IL10N&MockObject */
    private $l10n;

    /** @var ImagickSetupCheck */
    private $check;

    protected function setUp(): void
    {
      parent::setUp();
      self::$mockExtensionLoaded = [];

      $this->l10n = $this->createMock(IL10N::class);
      $this->l10n->method('t')
        ->willReturnCallback(fn(string $text, array $parameters = []) => vsprintf($text, $parameters));

      $this->check = new ImagickSetupCheck(
        $this->l10n
      );
    }

    public function testExtensionNotLoaded(): void
    {
      self::$mockExtensionLoaded['imagick'] = false;
      $check = new ImagickSetupCheck($this->l10n);

      $result = $check->run();

      $this->assertInstanceOf(SetupResult::class, $result);
      $this->assertEquals('info', $result->getSeverity());
      $this->assertStringContainsString('Imagick extension is not loaded', $result->getDescription());
      $this->assertNotNull($result->getDescription());
    }

    public function testExtensionLoaded(): void
    {
      self::$mockExtensionLoaded['imagick'] = true;
      $check = new ImagickSetupCheck($this->l10n);

      $result = $check->run();

      $this->assertInstanceOf(SetupResult::class, $result);
      $this->assertEquals('success', $result->getSeverity());
      $this->assertStringContainsString('Imagick extension is loaded', $result->getDescription());
    }

    public function testGetName(): void
    {
      $this->l10n->expects($this->once())
        ->method('t')
        ->with('Imagick PHP extension')
        ->willReturn('Imagick PHP extension');
      $this->assertEquals('Imagick PHP extension', $this->check->getName());
    }

    public function testGetCategory(): void
    {
      $this->assertEquals('system', $this->check->getCategory());
    }
  }
}
