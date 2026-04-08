<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Process;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Process\ListeningPidResolver;
use OCA\Libresign\Service\Process\ProcessManager;
use OCA\Libresign\Service\Process\ProcessSignaler;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
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
	 * @return array{0: ProcessManager, 1: array<string, string>}
	 */
	private function makeManagerWithStoredRegistry(?callable $factory = null): array {
		$storedByKey = [
			'process_registry' => '{}',
			'process_registry_hints' => '{}',
		];
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $_appId, string $key, string $default) use (&$storedByKey): string {
				return $storedByKey[$key] ?? $default;
			});

		$this->appConfig->method('setValueString')
			->willReturnCallback(function (string $_appId, string $key, string $value) use (&$storedByKey): bool {
				$storedByKey[$key] = $value;
				return true;
			});

		$manager = $factory
			? $factory($this->appConfig, $this->logger)
			: new ProcessManager($this->appConfig, $this->logger);

		return [$manager, $storedByKey];
	}

	public function testListRunningPurgesStoppedPids(): void {
		$storedByKey = [
			'process_registry' => '{}',
			'process_registry_hints' => '{}',
		];
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $appId, string $key, string $default) use (&$storedByKey): string {
				$this->assertSame(Application::APP_ID, $appId);
				$this->assertContains($key, ['process_registry', 'process_registry_hints']);
				return $storedByKey[$key] ?? $default;
			});

		$this->appConfig->method('setValueString')
			->willReturnCallback(function (string $_appId, string $key, string $value) use (&$storedByKey): bool {
				$storedByKey[$key] = $value;
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

	#[DataProvider('provideInvalidPids')]
	public function testRegisterIgnoresInvalidPid(int $invalidPid): void {
		[$manager] = $this->makeManagerWithStoredRegistry(fn (IAppConfig $appConfig, LoggerInterface $logger): ProcessManager => new class($appConfig, $logger) extends ProcessManager {
			public function isRunning(int $pid): bool {
				return true;
			}
		});

		$manager->register(self::WORKER_SOURCE, $invalidPid);

		$this->assertSame(0, $manager->countRunning(self::WORKER_SOURCE));
	}

	/**
	 * @return array<string, array{0: int}>
	 */
	public static function provideInvalidPids(): array {
		return [
			'zero' => [0],
			'negative' => [-9],
		];
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

	public function testFindRunningPidHydratesRegistryWhenHintExists(): void {
		$resolver = $this->createMock(ListeningPidResolver::class);
		$resolver->expects($this->once())
			->method('findListeningPids')
			->with(8888)
			->willReturn([777]);

		$signaler = $this->createMock(ProcessSignaler::class);
		$signaler->method('isRunning')
			->willReturn(true);

		[$manager] = $this->makeManagerWithStoredRegistry(
			fn (IAppConfig $appConfig, LoggerInterface $logger): ProcessManager
				=> new ProcessManager($appConfig, $logger, $signaler, $resolver)
		);

		$manager->setSourceHint(self::INSTALL_SOURCE, [
			'uri' => 'http://127.0.0.1:8888/api/v1/cfssl/',
			'port' => 8888,
		]);

		$actual = $manager->findRunningPid(self::INSTALL_SOURCE);

		$this->assertSame(777, $actual);
	}

	public function testFindRunningPidSkipsFallbackWhenNoHintExists(): void {
		$resolver = $this->createMock(ListeningPidResolver::class);
		$resolver->expects($this->never())
			->method('findListeningPids');

		$signaler = $this->createMock(ProcessSignaler::class);
		$signaler->method('isRunning')
			->willReturn(true);

		[$manager] = $this->makeManagerWithStoredRegistry(
			fn (IAppConfig $appConfig, LoggerInterface $logger): ProcessManager
				=> new ProcessManager($appConfig, $logger, $signaler, $resolver)
		);

		$this->assertSame(0, $manager->findRunningPid(self::INSTALL_SOURCE));
	}

	public function testIsRunningReturnsFalseForInvalidPid(): void {
		$manager = new ProcessManager($this->appConfig, $this->logger);

		$this->assertFalse($manager->isRunning(0));
		$this->assertFalse($manager->isRunning(-1));
	}

	public function testStopAllTerminatesAndUnregistersTrackedPids(): void {
		$storedByKey = [
			'process_registry' => '{}',
			'process_registry_hints' => '{}',
		];
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $_appId, string $key, string $default) use (&$storedByKey): string {
				return $storedByKey[$key] ?? $default;
			});

		$this->appConfig->method('setValueString')
			->willReturnCallback(function (string $_appId, string $key, string $value) use (&$storedByKey): bool {
				$storedByKey[$key] = $value;
				return true;
			});

		$manager = new class($this->appConfig, $this->logger) extends ProcessManager {
			/** @var int[] */
			public array $terminated = [];

			public function isRunning(int $pid): bool {
				return true;
			}

			public function stopPid(int $pid, int $signal = SIGTERM): bool {
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
		$storedByKey = [
			'process_registry' => '{}',
			'process_registry_hints' => '{}',
		];
		$this->appConfig->method('getValueString')
			->willReturnCallback(function (string $_appId, string $key, string $default) use (&$storedByKey): string {
				return $storedByKey[$key] ?? $default;
			});

		$this->appConfig->method('setValueString')
			->willReturnCallback(function (string $_appId, string $key, string $value) use (&$storedByKey): bool {
				$storedByKey[$key] = $value;
				return true;
			});

		$manager = new class($this->appConfig, $this->logger) extends ProcessManager {
			/** @var int[] */
			public array $signals = [];

			public function isRunning(int $pid): bool {
				return true;
			}

			public function stopPid(int $pid, int $signal = SIGTERM): bool {
				$this->signals[] = $signal;
				return true;
			}
		};

		$manager->register(self::WORKER_SOURCE, 333);
		$manager->register(self::WORKER_SOURCE, 444);

		$manager->stopAll(self::WORKER_SOURCE, SIGKILL);

		$this->assertSame([SIGKILL, SIGKILL], $manager->signals);
	}

	public function testFindListeningPidsDelegatesToResolver(): void {
		$resolver = $this->createMock(ListeningPidResolver::class);
		$resolver->expects($this->once())
			->method('findListeningPids')
			->with(8888)
			->willReturn([111, 222]);

		$signaler = $this->createMock(ProcessSignaler::class);

		$manager = new ProcessManager($this->appConfig, $this->logger, $signaler, $resolver);

		$this->assertSame([111, 222], $manager->findListeningPids(8888));
	}

	#[DataProvider('provideIsRunningDelegation')]
	public function testIsRunningDelegatesToSignaler(int $pid, bool $expected): void {
		$resolver = $this->createMock(ListeningPidResolver::class);
		$signaler = $this->createMock(ProcessSignaler::class);
		$signaler->expects($this->once())
			->method('isRunning')
			->with($pid)
			->willReturn($expected);

		$manager = new ProcessManager($this->appConfig, $this->logger, $signaler, $resolver);

		$this->assertSame($expected, $manager->isRunning($pid));
	}

	/**
	 * @return array<string, array{0: int, 1: bool}>
	 */
	public static function provideIsRunningDelegation(): array {
		return [
			'running pid' => [111, true],
			'not running pid' => [222, false],
		];
	}
}
