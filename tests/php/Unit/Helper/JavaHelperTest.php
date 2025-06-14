<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Helper;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Helper\JavaHelper;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class JavaHelperTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAppConfig $appConfig;
	private IL10N|MockObject $l10n;
	private LoggerInterface|MockObject $logger;

	public function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->getMockAppConfig();
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function getInstance(array $methods = []): MockObject|JavaHelper {
		if (empty($methods)) {
			return new JavaHelper(
				$this->appConfig,
				$this->l10n,
				$this->logger
			);
		}
		return $this->getMockBuilder(JavaHelper::class)
			->setConstructorArgs([
				$this->appConfig,
				$this->l10n,
				$this->logger,
			])
			->onlyMethods($methods)
			->getMock();
	}

	public function testInitSkipsWhenUtf8AlreadySet(): void {
		$this->logger->expects($this->never())->method('warning');

		$helper = $this->getInstance(['isNonUTF8Locale']);
		$helper->method('isNonUTF8Locale')->willReturn(false);

		$helper->init();
	}

	public function testInitSetsUtf8LocaleIfMissing(): void {
		$helper = $this->getInstance(['isNonUTF8Locale']);
		$helper->method('isNonUTF8Locale')->willReturn(true);
		$this->logger->expects($this->once())->method('warning');
		$helper->init();
	}

	public function testGetJavaPathTriggersInit(): void {
		$this->appConfig->setValueString(Application::APP_ID, 'java_path', '/usr/bin/java');

		$this->logger->expects($this->never())->method('warning');

		$helper = $this->getInstance(['isNonUTF8Locale']);
		$helper->method('isNonUTF8Locale')->willReturn(false);

		$path = $helper->getJavaPath();
		$this->assertSame('/usr/bin/java', $path);
	}
}
