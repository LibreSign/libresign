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
use OCA\Libresign\Service\Install\DependencyDownloader;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\SignSetupService;
use OCA\Libresign\Service\Process\ProcessManager;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class InstallServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private ICacheFactory&MockObject $cacheFactory;
	private DependencyDownloader&MockObject $dependencyDownloader;
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
		$this->dependencyDownloader = $this->createMock(DependencyDownloader::class);
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
			$this->dependencyDownloader,
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

	/**
	 * @runInSeparateProcess
	 */
	public function testGetFolderReplacesStaleResourceContents(): void {
		$installService = \OCP\Server::get(InstallService::class);
		$folder = self::invokePrivate($installService, 'getFolder', ['installer-stale-test']);
		$folder->newFile('old-file', 'old');

		$cleanFolder = self::invokePrivate(
			$installService,
			'getFolder',
			['installer-stale-test', null, true],
		);

		$this->assertSame([], $cleanFolder->getDirectoryListing());
	}

	public function testIsDownloadWipChecksResourcesAfterEmptyProgress(): void {
		$cache = $this->createMock(ICache::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cacheFactory->method('createDistributed')->willReturn($cache);
		$this->clientService = $this->createMock(IClientService::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->ignSetupService = $this->createMock(SignSetupService::class);
		$this->appDataFactory = $this->createMock(IAppDataFactory::class);
		$this->caIdentifierService = $this->createMock(CaIdentifierService::class);
		$this->processManager = $this->createMock(ProcessManager::class);

		$cache->method('get')
			->willReturnCallback(static fn (string $key): ?array => match ($key) {
				'libresign-asyncDownloadProgress-java' => null,
				'libresign-asyncDownloadProgress-jsignpdf' => ['pid' => 123],
				default => null,
			});
		$this->processManager->method('findRunningPid')->willReturn(123);

		$installService = new InstallService(
			$this->cacheFactory,
			$this->dependencyDownloader,
			$this->certificateEngineFactory,
			$this->config,
			$this->appConfig,
			$this->logger,
			$this->ignSetupService,
			$this->appDataFactory,
			$this->caIdentifierService,
			$this->processManager,
		);

		$this->assertTrue($installService->isDownloadWip());
	}

	public function testGetInstallPidReadsMatchingPidFromRegistry(): void {
		$installService = $this->getInstallService();
		$installService->setResource('cfssl');

		$this->processManager->expects($this->once())
			->method('findRunningPid')
			->with('install', $this->callback('is_callable'))
			->willReturnCallback(fn (string $_source, callable $filter): int => $filter([
				'pid' => 123,
				'context' => ['resource' => 'cfssl'],
				'createdAt' => 123,
			]) ? 123 : 0);

		$actual = self::invokePrivate($installService, 'getInstallPid');

		$this->assertSame(123, $actual);
	}

	public function testGetInstallPidIgnoresSameResourceFromAnotherArchitecture(): void {
		$installService = $this->getInstallService();
		$installService->setArchitecture('x86_64');
		$installService->setResource('cfssl');

		$this->processManager->expects($this->once())
			->method('findRunningPid')
			->with('install', $this->callback('is_callable'))
			->willReturnCallback(fn (string $_source, callable $filter): int => $filter([
				'pid' => 123,
				'context' => [
					'resource' => 'cfssl',
					'architecture' => 'aarch64',
					'distro' => $installService->getLinuxDistributionToDownloadJava(),
				],
				'createdAt' => 123,
			]) ? 123 : 0);

		$actual = self::invokePrivate($installService, 'getInstallPid');

		$this->assertSame(0, $actual);
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
			->willReturnCallback(fn (string $_source, callable $filter): int => $filter([
				'pid' => 321,
				'context' => ['resource' => 'cfssl'],
				'createdAt' => 123,
			]) ? 321 : 0);

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

		$installService = $this->getInstallServiceWithProcess();
		$installService->setArchitecture('amd64');
		$installService->setResource('cfssl');
		$installService->expects($this->once())
			->method('createProcess')
			->with([
				\OC::$SERVERROOT . '/occ',
				'libresign:install',
				'--cfssl',
				'--architecture=x86_64',
			])
			->willReturn($process);

		$this->processManager->expects($this->once())
			->method('register')
			->with('install', 321, [
				'resource' => 'cfssl',
				'architecture' => $installService->getArchitecture(),
				'distro' => $installService->getLinuxDistributionToDownloadJava(),
			]);

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

		$installService = $this->getInstallServiceWithProcess();
		$installService->setResource('cfssl');
		$installService->expects($this->once())
			->method('createProcess')
			->willReturn($process);

		$this->processManager->expects($this->never())
			->method('register');
		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('Error to get PID of background install process'));

		self::invokePrivate($installService, 'runAsync');
	}

	public function testRunAsyncPreservesJavaArchitectureAndDistro(): void {
		$process = $this->createMock(Process::class);
		$process->method('getPid')->willReturn(456);

		$installService = $this->getInstallServiceWithProcess();
		$installService->setArchitecture('arm64');
		$installService->setDistro('alpine-linux');
		$installService->setResource('java');
		$installService->expects($this->once())
			->method('createProcess')
			->with([
				\OC::$SERVERROOT . '/occ',
				'libresign:install',
				'--java',
				'--architecture=aarch64',
				'--distro=alpine-linux',
			])
			->willReturn($process);

		$this->processManager->expects($this->once())
			->method('register')
			->with('install', 456, [
				'resource' => 'java',
				'architecture' => 'aarch64',
				'distro' => 'alpine-linux',
			]);

		self::invokePrivate($installService, 'runAsync');
	}

	private function getInstallServiceWithProcess(): InstallService&MockObject {
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
				$this->dependencyDownloader,
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

		return $installService;
	}
}
