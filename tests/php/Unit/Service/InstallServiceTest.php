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
use OCA\Libresign\Service\Install\InstallProcessManager;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\InstallTarget;
use OCA\Libresign\Service\Install\SignSetupService;
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
	private InstallProcessManager&MockObject $installProcessManager;

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
		$this->installProcessManager = $this->createMock(InstallProcessManager::class);
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
			$this->installProcessManager,
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
		$this->installProcessManager = $this->createMock(InstallProcessManager::class);

		$cache->method('get')
			->willReturnCallback(static fn (string $key): ?array => match ($key) {
				'libresign-asyncDownloadProgress-java' => null,
				'libresign-asyncDownloadProgress-jsignpdf' => ['pid' => 123],
				default => null,
			});
		$this->installProcessManager->method('findRunningPid')->willReturn(123);

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
			$this->installProcessManager,
		);

		$this->assertTrue($installService->isDownloadWip());
	}


	public function testAsyncJavaInstallDelegatesToProcessManager(): void {
		$installService = $this->getInstallService();
		$this->appConfig->method('getValueString')
			->with('libresign', 'signature_engine', 'JSignPdf')
			->willReturn('JSignPdf');

		$this->installProcessManager->expects($this->once())
			->method('start')
			->with(
				'java',
				$this->callback(
					static fn ($target): bool
						=> $target->architecture() === InstallTarget::normalizeArchitecture(php_uname('m')),
				),
			)
			->willReturn(123);

		$installService->installJava(true);
	}


}
