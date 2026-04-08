<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Process;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Process\ProcessManager;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class ProcessManagerTest extends TestCase {
	private const INSTALL_SOURCE = 'install';
	private const WORKER_SOURCE = 'worker';

	private IAppConfig&MockObject $appConfig;
	private LoggerInterface&MockObject $logger;

	public function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	/**
	 * @return array{0: ProcessManager, 1: string}
	 */
	private function makeManagerWithStoredRegistry(?callable $factory = null): array {
		$stored = '{}';
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $_appId, string $_key, string $default) use (&$stored): string {
				return $stored ?: $default;
			});

		$this->appConfig->method('setValueString')
			->willReturnCallback(function (string $_appId, string $_key, string $value) use (&$stored): bool {
				$stored = $value;
				return true;
			});

		$manager = $factory
			? $factory($this->appConfig, $this->logger)
			: new ProcessManager($this->appConfig, $this->logger);

		return [$manager, $stored];
	}

	public function testListRunningPurgesStoppedPids(): void {
		$stored = '{}';
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $appId, string $key, string $default) use (&$stored): string {
				$this->assertSame(Application::APP_ID, $appId);
				$this->assertSame('process_registry', $key);
				return $stored ?: $default;
			});

		$this->appConfig->method('setValueString')
			->willReturnCallback(function (string $_appId, string $_key, string $value) use (&$stored): bool {
				$stored = $value;
				return true;
			});

		$manager = new class($this->appConfig, $this->logger) extends ProcessManager {
			public function isRunning(int $pid): bool {
				return $pid === 111;
			}
		};

		$manager->register(self::INSTALL_SOURCE, 111, ['resource' => 'cfssl']);
		$manager->register(self::INSTALL_SOURCE, 222, ['resource' => 'java']);

		$running = $manager->listRunning(self::INSTALL_SOURCE);

		$this->assertCount(1, $running);
		$this->assertSame(111, $running[0]['pid']);
	}

	public function testRegisterIgnoresInvalidPid(): void {
		[$manager] = $this->makeManagerWithStoredRegistry(fn (IAppConfig $appConfig, LoggerInterface $logger): ProcessManager => new class($appConfig, $logger) extends ProcessManager {
			public function isRunning(int $pid): bool {
				return true;
			}
		});

		$manager->register(self::WORKER_SOURCE, 0);
		$manager->register(self::WORKER_SOURCE, -9);

		$this->assertSame(0, $manager->countRunning(self::WORKER_SOURCE));
	}

	public function testFindRunningPidReturnsFirstWhenNoFilterProvided(): void {
		[$manager] = $this->makeManagerWithStoredRegistry(fn (IAppConfig $appConfig, LoggerInterface $logger): ProcessManager => new class($appConfig, $logger) extends ProcessManager {
			public function isRunning(int $pid): bool {
				return true;
			}
		});

		$manager->register(self::INSTALL_SOURCE, 111, ['resource' => 'cfssl']);
		$manager->register(self::INSTALL_SOURCE, 222, ['resource' => 'java']);

		$this->assertSame(111, $manager->findRunningPid(self::INSTALL_SOURCE));
	}

	public function testFindRunningPidAppliesFilterAgainstContext(): void {
		[$manager] = $this->makeManagerWithStoredRegistry(fn (IAppConfig $appConfig, LoggerInterface $logger): ProcessManager => new class($appConfig, $logger) extends ProcessManager {
			public function isRunning(int $pid): bool {
				return true;
			}
		});

		$manager->register(self::INSTALL_SOURCE, 111, ['resource' => 'cfssl']);
		$manager->register(self::INSTALL_SOURCE, 222, ['resource' => 'java']);

		$actual = $manager->findRunningPid(
			self::INSTALL_SOURCE,
			fn (array $entry): bool => ($entry['context']['resource'] ?? '') === 'java',
		);

		$this->assertSame(222, $actual);
	}

	public function testFindRunningPidReturnsZeroWhenNothingMatchesFilter(): void {
		[$manager] = $this->makeManagerWithStoredRegistry(fn (IAppConfig $appConfig, LoggerInterface $logger): ProcessManager => new class($appConfig, $logger) extends ProcessManager {
			public function isRunning(int $pid): bool {
				return true;
			}
		});

		$manager->register(self::INSTALL_SOURCE, 111, ['resource' => 'cfssl']);

		$actual = $manager->findRunningPid(
			self::INSTALL_SOURCE,
			fn (array $entry): bool => ($entry['context']['resource'] ?? '') === 'java',
		);

		$this->assertSame(0, $actual);
	}

	public function testIsRunningReturnsFalseForInvalidPid(): void {
		$manager = new ProcessManager($this->appConfig, $this->logger);

		$this->assertFalse($manager->isRunning(0));
		$this->assertFalse($manager->isRunning(-1));
	}

	public function testStopAllTerminatesAndUnregistersTrackedPids(): void {
		$stored = '{}';
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $_appId, string $_key, string $default) use (&$stored): string {
				return $stored ?: $default;
			});

		$this->appConfig->method('setValueString')
			->willReturnCallback(function (string $_appId, string $_key, string $value) use (&$stored): bool {
				$stored = $value;
				return true;
			});

		$manager = new class($this->appConfig, $this->logger) extends ProcessManager {
			/** @var int[] */
			public array $terminated = [];

			public function isRunning(int $pid): bool {
				return true;
			}

			protected function terminate(int $pid, int $signal): bool {
				$this->terminated[] = $pid;
				return true;
			}
		};

		$manager->register(self::WORKER_SOURCE, 333);
		$manager->register(self::WORKER_SOURCE, 444);

		$stopped = $manager->stopAll(self::WORKER_SOURCE);

		$this->assertSame(2, $stopped);
		$this->assertSame([333, 444], $manager->terminated);
		$this->assertSame(0, $manager->countRunning(self::WORKER_SOURCE));
	}

	public function testStopAllUsesProvidedSignal(): void {
		$stored = '{}';
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $_appId, string $_key, string $default) use (&$stored): string {
				return $stored ?: $default;
			});

		$this->appConfig->method('setValueString')
			->willReturnCallback(function (string $_appId, string $_key, string $value) use (&$stored): bool {
				$stored = $value;
				return true;
			});

		$manager = new class($this->appConfig, $this->logger) extends ProcessManager {
			/** @var int[] */
			public array $signals = [];

			public function isRunning(int $pid): bool {
				return true;
			}

			protected function terminate(int $pid, int $signal): bool {
				$this->signals[] = $signal;
				return true;
			}
		};

		$manager->register(self::WORKER_SOURCE, 333);
		$manager->register(self::WORKER_SOURCE, 444);

		$manager->stopAll(self::WORKER_SOURCE, SIGKILL);

		$this->assertSame([SIGKILL, SIGKILL], $manager->signals);
	}
}
