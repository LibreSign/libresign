<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use bovigo\vfs\vfsStream;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\SignSetupService;
use OCA\Libresign\Service\Process\ProcessManager;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\ICacheFactory;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\BufferedOutput;

final class InstallServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private ICacheFactory&MockObject $cacheFactory;
	private IClientService&MockObject $clientService;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private IConfig&MockObject $config;
	private IAppConfig&MockObject $appConfig;
	private LoggerInterface&MockObject $logger;
	private SignSetupService&MockObject $ignSetupService;
	private IAppDataFactory&MockObject $appDataFactory;
	private CaIdentifierService&MockObject $caIdentifierService;
	private ProcessManager&MockObject $processManager;

	public function setUp(): void {
		parent::setUp();
	}

	protected function getInstallService(): InstallService {
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->ignSetupService = $this->createMock(SignSetupService::class);
		$this->appDataFactory = $this->createMock(IAppDataFactory::class);
		$this->caIdentifierService = $this->createMock(CaIdentifierService::class);
		$this->processManager = $this->createMock(ProcessManager::class);
		return new InstallService(
			$this->cacheFactory,
			$this->clientService,
			$this->certificateEngineFactory,
			$this->config,
			$this->appConfig,
			$this->logger,
			$this->ignSetupService,
			$this->appDataFactory,
			$this->caIdentifierService,
			$this->processManager,
		);
	}

	/**
	 * @dataProvider providerDownloadCli
	 */
	public function testDownloadCli(string $url, string $filename, string $content, string $hash, string $algorithm, string $expectedOutput): void {
		$installService = $this->getInstallService();
		$output = new BufferedOutput();
		$installService->setOutput($output);

		if ($content) {
			vfsStream::setup('download');
			$path = 'vfs://download/dummy.svg';
			file_put_contents($path, $content);
		} else {
			$path = '';
		}

		self::invokePrivate($installService, 'downloadCli', [$url, $filename, $path, $hash, $algorithm]);
		$actual = $output->fetch();
		$this->assertEquals($expectedOutput, $actual);
	}

	public static function providerDownloadCli(): array {
		return [
			[
				'url' => 'http://localhost/apps/libresign/img/app.svg',
				'filename' => 'app.svg',
				'content' => '',
				'hash' => '',
				'algorithm' => 'md5',
				'expectedOutput' => <<<EXPECTEDOUTPUT
					Downloading app.svg...
					    0 [>---------------------------]
					Failure on download app.svg, empty file, try again

					EXPECTEDOUTPUT
			],
			[
				'url' => 'http://localhost/apps/libresign/img/appInvalid.svg',
				'filename' => 'appInvalid.svg',
				'content' => 'content',
				'hash' => 'invalidContent',
				'algorithm' => 'md5',
				'expectedOutput' => <<<EXPECTEDOUTPUT
					Downloading appInvalid.svg...
					    0 [>---------------------------]
					Failure on download appInvalid.svg try again
					Invalid md5

					EXPECTEDOUTPUT
			],
			[
				'url' => 'http://localhost/apps/libresign/img/appInvalid.svg',
				'filename' => 'appInvalid.svg',
				'content' => 'content',
				'hash' => 'invalidContent',
				'algorithm' => 'sha256',
				'expectedOutput' => <<<EXPECTEDOUTPUT
					Downloading appInvalid.svg...
					    0 [>---------------------------]
					Failure on download appInvalid.svg try again
					Invalid sha256

					EXPECTEDOUTPUT
			],
			[
				'url' => 'http://localhost/apps/libresign/img/validContent.svg',
				'filename' => 'validContent.svg',
				'content' => 'content',
				'hash' => hash('sha256', 'content'),
				'algorithm' => 'sha256',
				'expectedOutput' => <<<EXPECTEDOUTPUT
					Downloading validContent.svg...
					    0 [>---------------------------]

					EXPECTEDOUTPUT
			],
		];
	}

	/**
	 * @dataProvider providerGetFolder
	 * @runInSeparateProcess
	 */
	public function testGetFolder(string $architecture, string $path, string $expectedFolderName): void {
		$install = \OCP\Server::get(\OCA\Libresign\Service\Install\InstallService::class);
		if (!empty($architecture)) {
			$install->setArchitecture($architecture);
		}
		$folder = self::invokePrivate($install, 'getFolder', [$path]);
		$this->assertEquals($folder->getName(), $expectedFolderName);
	}

	public static function providerGetFolder(): array {
		return [
			['', '', php_uname('m')],
			['', 'test', 'test'],
			['', 'test/folder1', 'folder1'],
			['', 'test/folder1/folder2', 'folder2'],
			['aarch64', '', 'aarch64'],
			['aarch64', 'test', 'test'],
			['aarch64', 'test/folder1', 'folder1'],
			['aarch64', 'test/folder1/folder2', 'folder2'],
			['x86_64', '', 'x86_64'],
			['x86_64', 'test', 'test'],
			['x86_64', 'test/folder1', 'folder1'],
			['x86_64', 'test/folder1/folder2', 'folder2'],
		];
	}

	public function testGetInstallPidReadsMatchingPidFromRegistry(): void {
		$installService = $this->getInstallService();
		$installService->setResource('cfssl');

		$this->processManager->expects($this->once())
			->method('findRunningPid')
			->with('install', $this->callback('is_callable'))
			->willReturnCallback(function (string $_source, callable $filter): int {
				return $filter([
					'pid' => 123,
					'context' => ['resource' => 'cfssl'],
					'createdAt' => 123,
				]) ? 123 : 0;
			});

		$actual = self::invokePrivate($installService, 'getInstallPid');

		$this->assertSame(123, $actual);
	}

	public function testGetInstallPidValidatesRequestedPidAgainstResource(): void {
		$installService = $this->getInstallService();
		$installService->setResource('cfssl');

		$this->processManager->expects($this->once())
			->method('findRunningPid')
			->with('install', $this->callback('is_callable'))
			->willReturn(0);

		$this->processManager->expects($this->once())
			->method('unregister')
			->with('install', 123);

		$actual = self::invokePrivate($installService, 'getInstallPid', [123]);

		$this->assertSame(0, $actual);
	}

	public function testGetInstallPidKeepsRequestedPidWhenResourceMatches(): void {
		$installService = $this->getInstallService();
		$installService->setResource('cfssl');

		$this->processManager->expects($this->once())
			->method('findRunningPid')
			->with('install', $this->callback('is_callable'))
			->willReturnCallback(function (string $_source, callable $filter): int {
				return $filter([
					'pid' => 321,
					'context' => ['resource' => 'cfssl'],
					'createdAt' => 123,
				]) ? 321 : 0;
			});

		$this->processManager->expects($this->never())
			->method('unregister');

		$actual = self::invokePrivate($installService, 'getInstallPid', [321]);

		$this->assertSame(321, $actual);
	}

	public function testRunAsyncRegistersPidWhenProcessStarts(): void {
		$process = $this->createMock(Process::class);
		$process->expects($this->once())
			->method('setOptions')
			->with(['create_new_console' => true]);
		$process->expects($this->once())
			->method('setTimeout')
			->with(null);
		$process->expects($this->once())
			->method('start');
		$process->expects($this->once())
			->method('getPid')
			->willReturn(321);

		$installService = $this->getInstallServiceWithProcess($process);
		$installService->setResource('cfssl');

		$this->processManager->expects($this->once())
			->method('register')
			->with('install', 321, ['resource' => 'cfssl']);

		self::invokePrivate($installService, 'runAsync');
	}

	public function testRunAsyncLogsErrorWhenPidIsMissing(): void {
		$process = $this->createMock(Process::class);
		$process->expects($this->once())
			->method('setOptions')
			->with(['create_new_console' => true]);
		$process->expects($this->once())
			->method('setTimeout')
			->with(null);
		$process->expects($this->once())
			->method('start');
		$process->expects($this->once())
			->method('getPid')
			->willReturn(null);

		$installService = $this->getInstallServiceWithProcess($process);
		$installService->setResource('cfssl');

		$this->processManager->expects($this->never())
			->method('register');
		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('Error to get PID of background install proccess'));

		self::invokePrivate($installService, 'runAsync');
	}

	private function getInstallServiceWithProcess(Process $process): InstallService&MockObject {
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->ignSetupService = $this->createMock(SignSetupService::class);
		$this->appDataFactory = $this->createMock(IAppDataFactory::class);
		$this->caIdentifierService = $this->createMock(CaIdentifierService::class);
		$this->processManager = $this->createMock(ProcessManager::class);

		$installService = $this->getMockBuilder(InstallService::class)
			->setConstructorArgs([
				$this->cacheFactory,
				$this->clientService,
				$this->certificateEngineFactory,
				$this->config,
				$this->appConfig,
				$this->logger,
				$this->ignSetupService,
				$this->appDataFactory,
				$this->caIdentifierService,
				$this->processManager,
			])
			->onlyMethods(['createProcess'])
			->getMock();

		$installService->method('createProcess')
			->willReturn($process);

		return $installService;
	}
}
