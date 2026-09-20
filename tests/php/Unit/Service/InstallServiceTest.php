<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\CertificateEngine\CertificateEngineFactory;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\Install\DependencyDownloader;
use OCA\Libresign\Service\Install\DependencyStorage;
use OCA\Libresign\Service\Install\InstallProcessManager;
use OCA\Libresign\Service\Install\InstallProgressStore;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\InstallTarget;
use OCA\Libresign\Service\Install\SignSetupService;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class InstallServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private InstallProgressStore&MockObject $progressStore;
	private DependencyDownloader&MockObject $dependencyDownloader;
	private CertificateEngineFactory&MockObject $certificateEngineFactory;
	private IAppConfig&MockObject $appConfig;
	private LoggerInterface&MockObject $logger;
	private SignSetupService&MockObject $signSetupService;
	private DependencyStorage&MockObject $dependencyStorage;
	private CaIdentifierService&MockObject $caIdentifierService;
	private InstallProcessManager&MockObject $installProcessManager;

	protected function getInstallService(): InstallService {
		$this->progressStore = $this->createMock(InstallProgressStore::class);
		$this->dependencyDownloader = $this->createMock(DependencyDownloader::class);
		$this->certificateEngineFactory = $this->createMock(CertificateEngineFactory::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->signSetupService = $this->createMock(SignSetupService::class);
		$this->dependencyStorage = $this->createMock(DependencyStorage::class);
		$this->caIdentifierService = $this->createMock(CaIdentifierService::class);
		$this->installProcessManager = $this->createMock(InstallProcessManager::class);

		return new InstallService(
			$this->progressStore,
			$this->dependencyDownloader,
			$this->certificateEngineFactory,
			$this->appConfig,
			$this->logger,
			$this->signSetupService,
			$this->dependencyStorage,
			$this->caIdentifierService,
			$this->installProcessManager,
		);
	}

	public function testIsDownloadWipChecksResourcesAfterEmptyProgress(): void {
		$installService = $this->getInstallService();

		$this->progressStore->method('get')
			->willReturnCallback(
				static fn (InstallTarget $_target, string $resource): array
					=> $resource === 'jsignpdf' ? ['pid' => 123] : [],
			);
		$this->installProcessManager->method('findRunningPid')
			->willReturnCallback(
				static fn (string $resource): int => $resource === 'jsignpdf' ? 123 : 0,
			);

		$this->assertTrue($installService->isDownloadWip());
	}


	public function testAsyncJavaInstallDelegatesResourceAndNormalizedTarget(): void {
		$installService = $this->getInstallService();
		$this->appConfig->method('getValueString')
			->with(Application::APP_ID, 'signature_engine', 'JSignPdf')
			->willReturn('JSignPdf');

		$this->installProcessManager->expects($this->once())
			->method('start')
			->with(
				'java',
				$this->callback(
					static fn (InstallTarget $target): bool
						=> $target->architecture() === InstallTarget::normalizeArchitecture(php_uname('m')),
				),
			)
			->willReturn(123);

		$installService->installJava(true);
	}

	public function testAsyncInstallStoresActionableErrorWhenProcessDoesNotStart(): void {
		$installService = $this->getInstallService();
		$this->progressStore->method('get')->willReturn([]);

		$this->appConfig->method('getValueString')
			->with(Application::APP_ID, 'signature_engine', 'JSignPdf')
			->willReturn('JSignPdf');
		$this->installProcessManager->method('start')->willReturn(null);

		$this->progressStore->expects($this->once())
			->method('set')
			->with(
				$this->isInstanceOf(InstallTarget::class),
				'java',
				$this->callback(
					static fn (array $data): bool
						=> str_contains($data['error'] ?? '', 'could not start the background installer'),
				),
			);

		$installService->installJava(true);
	}
}
