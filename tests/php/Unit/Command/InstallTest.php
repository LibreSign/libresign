<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Command;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Command\Install;
use OCA\Libresign\Service\Install\InstallService;
use OCP\IAppConfig;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class InstallTest extends TestCase {
	public function testAllInstallsEveryResourceOnceWithoutChangingArchitecture(): void {
		$installService = $this->createMock(InstallService::class);
		$logger = $this->createMock(LoggerInterface::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$config = $this->createMock(IConfig::class);

		$config->method('getSystemValueBool')
			->with('debug', false)
			->willReturn(false);

		$resources = ['java', 'jsignpdf', 'pdftk', 'cfssl'];
		$installService->method('getAvailableResources')->willReturn($resources);
		$installService->expects($this->never())->method('setArchitecture');

		$installed = [];
		$installService->expects($this->exactly(count($resources)))
			->method('install')
			->willReturnCallback(static function (string $resource) use (&$installed): void {
				$installed[] = $resource;
			});

		$appConfig->method('getValueString')
			->with(Application::APP_ID, 'certificate_engine', 'openssl')
			->willReturn('cfssl');

		$tester = new CommandTester(new Install(
			$installService,
			$logger,
			$appConfig,
			$config,
		));

		$status = $tester->execute(['--all' => true]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertSame($resources, $installed);
	}

	public function testArchitectureAliasIsNormalizedForSingleResource(): void {
		$installService = $this->createMock(InstallService::class);
		$logger = $this->createMock(LoggerInterface::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$config = $this->createMock(IConfig::class);

		$config->method('getSystemValueBool')->willReturn(false);
		$installService->method('getAvailableResources')
			->willReturn(['java', 'jsignpdf', 'pdftk', 'cfssl']);

		$installService->expects($this->once())
			->method('setArchitecture')
			->with('x86_64')
			->willReturnSelf();
		$installService->expects($this->once())
			->method('install')
			->with('java');

		$tester = new CommandTester(new Install(
			$installService,
			$logger,
			$appConfig,
			$config,
		));

		$status = $tester->execute([
			'--java' => true,
			'--architecture' => 'amd64',
		]);

		$this->assertSame(Command::SUCCESS, $status);
	}
}
