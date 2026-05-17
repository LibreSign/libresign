<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Handler\CertificateEngine;

use OCA\Libresign\Db\CrlMapper;
use OCA\Libresign\Handler\CertificateEngine\CfsslHandler;
use OCA\Libresign\Handler\CfsslServerHandler;
use OCA\Libresign\Service\CaIdentifierService;
use OCA\Libresign\Service\CertificatePolicyService;
use OCA\Libresign\Service\Crl\CrlRevocationChecker;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Process\ProcessManager;
use OCA\Libresign\Tests\Unit\TestCase;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class CfsslHandlerTest extends TestCase {
	private const PROCESS_SOURCE = 'cfssl';

	private ProcessManager&MockObject $processManager;

	public function setUp(): void {
		parent::setUp();
		$this->processManager = $this->createMock(ProcessManager::class);
	}

	public function testGetServerPidReadsFromRegistry(): void {
		$handler = $this->createHandler();

		$this->processManager->expects($this->once())
			->method('findRunningPid')
			->with(self::PROCESS_SOURCE, $this->callback('is_callable'))
			->willReturnCallback(function (string $_source, callable $filter): int {
				return $filter([
					'pid' => 302,
					'context' => ['uri' => CfsslHandler::CFSSL_URI],
					'createdAt' => 123,
				]) ? 302 : 0;
			});

		$actual = self::invokePrivate($handler, 'getServerPid');

		$this->assertSame(302, $actual);
	}

	public function testScopedProcessClassIsAvailable(): void {
		$this->assertTrue(class_exists(Process::class));
	}

	public function testStopIfRunningKillsRegisteredAndPortListenerPids(): void {
		$unregisteredPids = [];
		$stoppedPids = [];
		$uri = CfsslHandler::CFSSL_URI;
		$port = 8888;

		$this->processManager->expects($this->once())
			->method('setSourceHint')
			->with(self::PROCESS_SOURCE, [
				'uri' => $uri,
				'port' => $port,
			]);

		$this->processManager->expects($this->once())
			->method('findRunningPid')
			->with(self::PROCESS_SOURCE, $this->callback('is_callable'))
			->willReturn(321);

		$this->processManager->expects($this->once())
			->method('listRunning')
			->with(self::PROCESS_SOURCE)
			->willReturn([
				['pid' => 321, 'context' => ['uri' => $uri], 'createdAt' => 1],
				['pid' => 654, 'context' => ['uri' => $uri], 'createdAt' => 2],
			]);

		$this->processManager->expects($this->exactly(2))
			->method('unregister')
			->willReturnCallback(function (string $source, int $pid) use (&$unregisteredPids): void {
				$this->assertSame(self::PROCESS_SOURCE, $source);
				$unregisteredPids[] = $pid;
			});

		$this->processManager->expects($this->exactly(2))
			->method('stopPid')
			->withAnyParameters()
			->willReturnCallback(function (int $pid, int $signal) use (&$stoppedPids): bool {
				$this->assertSame(SIGKILL, $signal);
				$stoppedPids[] = $pid;
				return true;
			});

		$handler = $this->getMockBuilder(CfsslHandler::class)
			->setConstructorArgs($this->getConstructorArgs())
			->onlyMethods(['createProcess'])
			->getMock();

		$handler->method('createProcess')
			->willReturn($this->createMock(Process::class));

		self::invokePrivate($handler, 'stopIfRunning');

		sort($unregisteredPids);
		sort($stoppedPids);
		$this->assertSame([321, 654], $unregisteredPids);
		$this->assertSame([321, 654], $stoppedPids);
	}

	#[DataProvider('provideCheckBinariesErrorCases')]
	public function testCheckBinariesReturnsErrorForInvalidProcessState(
		bool $isSuccessful,
		string $output,
		string $expectedMessage,
	): void {
		$binary = tempnam(sys_get_temp_dir(), 'cfssl-bin-');
		$this->assertNotFalse($binary);

		$process = $this->createMock(Process::class);
		$process->expects($this->once())
			->method('run');
		$process->expects($this->once())
			->method('isSuccessful')
			->willReturn($isSuccessful);
		$process->expects($this->once())
			->method('getOutput')
			->willReturn($output);

		$handler = $this->createHandler($process, (string)$binary);
		$result = self::invokePrivate($handler, 'checkBinaries');

		$this->assertSame('error', $result[0]->jsonSerialize()['status']);
		$this->assertStringContainsString($expectedMessage, $result[0]->jsonSerialize()['message']);

		@unlink((string)$binary);
	}

	public function testCheckBinariesReturnsSuccessWhenProcessOutputIsValid(): void {
		$binary = tempnam(sys_get_temp_dir(), 'cfssl-bin-');
		$this->assertNotFalse($binary);

		$process = $this->createMock(Process::class);
		$process->expects($this->once())
			->method('run');
		$process->expects($this->once())
			->method('isSuccessful')
			->willReturn(true);
		$process->expects($this->once())
			->method('getOutput')
			->willReturn('Version: ' . InstallService::CFSSL_VERSION . "\nRuntime: go1.22\n");

		$handler = $this->createHandler($process, (string)$binary);
		$result = self::invokePrivate($handler, 'checkBinaries');

		$this->assertCount(3, $result);
		$this->assertSame('success', $result[0]->jsonSerialize()['status']);
		$this->assertSame('success', $result[1]->jsonSerialize()['status']);
		$this->assertSame('success', $result[2]->jsonSerialize()['status']);

		@unlink((string)$binary);
	}


	/**
	 * @return array<string, array{0: bool, 1: string, 2: string}>
	 */
	public static function provideCheckBinariesErrorCases(): array {
		return [
			'process failure without output' => [
				false,
				'',
				'Failed to run the command',
			],
			'invalid version output format' => [
				true,
				'cfssl version output without expected separators',
				'Failed to identify cfssl version',
			],
			'version mismatch' => [
				true,
				"Version: 0.0.1\nRuntime: go1.22\n",
				'Invalid version. Expected:',
			],
		];
	}

	private function createHandler(?Process $process = null, ?string $binary = null): CfsslHandler {
		$constructorArgs = $this->getConstructorArgs($binary);
		$process ??= $this->createMock(Process::class);

		$handler = $this->getMockBuilder(CfsslHandler::class)
			->setConstructorArgs($constructorArgs)
			->onlyMethods(['createProcess'])
			->getMock();

		$handler->method('createProcess')
			->willReturn($process);

		return $handler;
	}

	/**
	 * @return array<int, mixed>
	 */
	private function getConstructorArgs(?string $binary = null): array {
		$config = \OCP\Server::get(IConfig::class);
		$appConfig = $this->getMockAppConfigWithReset();
		if ($binary !== null) {
			$appConfigMock = $this->createMock(IAppConfig::class);
			$appConfigMock->method('getValueString')
				->willReturnCallback(function (string $appId, string $key, string $default = '') use ($binary): string {
					if ($appId === 'libresign' && $key === 'cfssl_bin') {
						return $binary;
					}
					return $default;
				});
			$appConfig = $appConfigMock;
		}
		$appDataFactory = \OCP\Server::get(IAppDataFactory::class);
		$dateTimeFormatter = \OCP\Server::get(IDateTimeFormatter::class);
		$tempManager = \OCP\Server::get(ITempManager::class);
		$certificatePolicyService = \OCP\Server::get(CertificatePolicyService::class);
		$urlGenerator = \OCP\Server::get(IURLGenerator::class);
		$caIdentifierService = \OCP\Server::get(CaIdentifierService::class);
		$policyService = \OCP\Server::get(PolicyService::class);
		$crlMapper = \OCP\Server::get(CrlMapper::class);
		$logger = \OCP\Server::get(LoggerInterface::class);
		$crlRevocationChecker = $this->createMock(CrlRevocationChecker::class);
		$cfsslServerHandler = $this->createMock(CfsslServerHandler::class);
		$cfsslServerHandler->expects($this->once())
			->method('configCallback');

		return [
			$config,
			$appConfig,
			$appDataFactory,
			$dateTimeFormatter,
			$tempManager,
			$cfsslServerHandler,
			$certificatePolicyService,
			$urlGenerator,
			$caIdentifierService,
			$policyService,
			$crlMapper,
			$logger,
			$crlRevocationChecker,
			$this->processManager,
		];
	}
}
