<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Command;

use InvalidArgumentException;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Command\Install;
use OCA\Libresign\Service\Install\InstallService;
use OCP\IAppConfig;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class InstallTest extends TestCase {
	private InstallService&MockObject $installService;
	private IAppConfig&MockObject $appConfig;
	private IConfig&MockObject $config;
	private CommandTester $tester;

	#[\Override]
	protected function setUp(): void {
		$this->installService = $this->createMock(InstallService::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getSystemValue')
			->with('debug', false)
			->willReturn(false);
		$this->installService->method('getAvailableResources')
			->willReturn(['java', 'jsignpdf', 'pdftk', 'cfssl']);

		$this->tester = new CommandTester(new Install(
			$this->installService,
			$this->createMock(LoggerInterface::class),
			$this->appConfig,
			$this->config,
		));
	}

	#[DataProvider('singleResourceProvider')]
	public function testInstallsRequestedResource(string $option, string $resource): void {
		$this->installService->expects($this->once())
			->method('install')
			->with($resource);

		$status = $this->tester->execute([$option => true]);

		$this->assertSame(Command::SUCCESS, $status);
	}

	public static function singleResourceProvider(): array {
		return [
			'java' => ['--java', 'java'],
			'jsignpdf' => ['--jsignpdf', 'jsignpdf'],
			'pdftk' => ['--pdftk', 'pdftk'],
			'cfssl' => ['--cfssl', 'cfssl'],
		];
	}

	#[DataProvider('architectureProvider')]
	public function testNormalizesArchitectureAlias(string $input, string $expected): void {
		$this->installService->expects($this->once())
			->method('setArchitecture')
			->with($expected)
			->willReturnSelf();
		$this->installService->expects($this->once())
			->method('install')
			->with('java');

		$status = $this->tester->execute([
			'--java' => true,
			'--architecture' => $input,
		]);

		$this->assertSame(Command::SUCCESS, $status);
	}

	public static function architectureProvider(): array {
		return [
			'x86_64' => ['x86_64', 'x86_64'],
			'amd64' => ['amd64', 'x86_64'],
			'aarch64' => ['aarch64', 'aarch64'],
			'arm64' => ['arm64', 'aarch64'],
		];
	}

	public function testAllInstallsEveryResourceOnceWithoutChangingArchitecture(): void {
		$resources = ['java', 'jsignpdf', 'pdftk', 'cfssl'];
		$this->installService->expects($this->never())->method('setArchitecture');

		$installed = [];
		$this->installService->expects($this->exactly(count($resources)))
			->method('install')
			->willReturnCallback(static function (string $resource) use (&$installed): void {
				$installed[] = $resource;
			});
		$this->appConfig->method('getValueString')
			->with(Application::APP_ID, 'certificate_engine', 'openssl')
			->willReturn('cfssl');

		$status = $this->tester->execute(['--all' => true]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertSame($resources, $installed);
	}

	#[DataProvider('currentDistroProvider')]
	public function testAllDistrosInstallsJavaForBothDistros(
		string $currentDistro,
		array $expectedDistros,
	): void {
		$this->installService->method('getLinuxDistributionToDownloadJava')
			->willReturn($currentDistro);

		$distros = [];
		$this->installService->expects($this->exactly(2))
			->method('setDistro')
			->willReturnCallback(function (string $distro) use (&$distros): InstallService {
				$distros[] = $distro;
				return $this->installService;
			});
		$this->installService->expects($this->exactly(2))
			->method('install')
			->with('java');

		$status = $this->tester->execute([
			'--java' => true,
			'--all-distros' => true,
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertSame($expectedDistros, $distros);
	}

	public static function currentDistroProvider(): array {
		return [
			'linux first keeps current last' => ['linux', ['alpine-linux', 'linux']],
			'alpine first keeps current last' => ['alpine-linux', ['linux', 'alpine-linux']],
		];
	}

	public function testRejectsDistroTogetherWithAllDistros(): void {
		$this->installService->expects($this->never())->method('install');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('--distro and --all-distros cannot be used together.');

		$this->tester->execute([
			'--java' => true,
			'--distro' => 'linux',
			'--all-distros' => true,
		]);
	}

	public function testFailsWhenNoResourceWasSelected(): void {
		$this->installService->expects($this->never())->method('install');

		$status = $this->tester->execute([]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString('Please inform what you want to install', $this->tester->getDisplay());
	}
}
