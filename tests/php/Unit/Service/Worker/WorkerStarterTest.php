<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Process\ProcessManager;
use OCA\Libresign\Service\Worker\WorkerStarter;
use OCA\Libresign\Tests\Unit\TestCase;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
use OCP\IBinaryFinder;
use PHPUnit\Framework\MockObject\MockObject;

class WorkerStarterTest extends TestCase {
	private IBinaryFinder&MockObject $binaryFinder;
	private ProcessManager&MockObject $processManager;

	public function setUp(): void {
		parent::setUp();
		$this->binaryFinder = $this->createMock(IBinaryFinder::class);
		$this->processManager = $this->createMock(ProcessManager::class);
	}

	private function makeProcessMock(?int $pid): Process&MockObject {
		$process = $this->createMock(Process::class);
		$process->expects($this->once())->method('setOptions')->with(['create_new_console' => true]);
		$process->expects($this->once())->method('setTimeout')->with(null);
		$process->expects($this->once())->method('start');
		$process->expects($this->once())->method('getPid')->willReturn($pid);
		return $process;
	}

	/** @return array<int, Process&MockObject> */
	private function makeProcessMocks(int $count, ?int $pid): array {
		$processes = [];
		for ($i = 0; $i < $count; $i++) {
			$processes[] = $this->makeProcessMock($pid);
		}
		return $processes;
	}

	public function testStartWorkersFallbacksToPhpAndRegistersPid(): void {
		$this->binaryFinder->expects($this->once())
			->method('findBinaryPath')
			->with('php')
			->willReturn(false);

		$callIndex = 0;
		$this->processManager->expects($this->exactly(2))
			->method('register')
			->willReturnCallback(function (string $source, int $pid) use (&$callIndex): void {
				$callIndex++;
				$this->assertSame('worker', $source);
				$this->assertSame($callIndex === 1 ? 101 : 202, $pid);
			});

		$workerStarter = new class($this->binaryFinder, $this->processManager, [$this->makeProcessMock(101), $this->makeProcessMock(202)]) extends WorkerStarter {
			/** @var Process[] */
			private array $processes;
			/** @var array<int, array<int, string>> */
			public array $commands = [];

			public function __construct(IBinaryFinder $binaryFinder, ProcessManager $processManager, array $processes) {
				parent::__construct($binaryFinder, $processManager);
				$this->processes = $processes;
			}

			protected function createProcess(array $command): Process {
				$this->commands[] = $command;
				return array_shift($this->processes);
			}
		};

		$workerStarter->startWorkers(2);

		$this->assertCount(2, $workerStarter->commands);
		$this->assertSame('php', $workerStarter->commands[0][0]);
		$this->assertSame('background-job:worker', $workerStarter->commands[0][2]);
	}

	public function testStartWorkersClampsMinimumToOne(): void {
		$this->binaryFinder->expects($this->once())
			->method('findBinaryPath')
			->with('php')
			->willReturn('/usr/bin/php');

		$this->processManager->expects($this->never())
			->method('register');

		$workerStarter = new class($this->binaryFinder, $this->processManager, [$this->makeProcessMock(0)]) extends WorkerStarter {
			/** @var Process[] */
			private array $processes;
			/** @var array<int, array<int, string>> */
			public array $commands = [];

			public function __construct(IBinaryFinder $binaryFinder, ProcessManager $processManager, array $processes) {
				parent::__construct($binaryFinder, $processManager);
				$this->processes = $processes;
			}

			protected function createProcess(array $command): Process {
				$this->commands[] = $command;
				return array_shift($this->processes);
			}
		};

		$workerStarter->startWorkers(0);

		$this->assertCount(1, $workerStarter->commands);
	}

	public function testStartWorkersClampsMaximumToThirtyTwo(): void {
		$this->binaryFinder->expects($this->once())
			->method('findBinaryPath')
			->with('php')
			->willReturn('/usr/bin/php');

		$this->processManager->expects($this->never())
			->method('register');

		$workerStarter = new class($this->binaryFinder, $this->processManager, $this->makeProcessMocks(32, null)) extends WorkerStarter {
			/** @var Process[] */
			private array $processes;
			/** @var array<int, array<int, string>> */
			public array $commands = [];

			public function __construct(IBinaryFinder $binaryFinder, ProcessManager $processManager, array $processes) {
				parent::__construct($binaryFinder, $processManager);
				$this->processes = $processes;
			}

			protected function createProcess(array $command): Process {
				$this->commands[] = $command;
				return array_shift($this->processes);
			}
		};

		$workerStarter->startWorkers(999);

		$this->assertCount(32, $workerStarter->commands);
	}

	public function testStartWorkersDoesNotRegisterWhenPidIsInvalid(): void {
		$this->binaryFinder->expects($this->once())
			->method('findBinaryPath')
			->with('php')
			->willReturn('/usr/bin/php');

		$this->processManager->expects($this->never())
			->method('register');

		$workerStarter = new class($this->binaryFinder, $this->processManager, [$this->makeProcessMock(0), $this->makeProcessMock(-1), $this->makeProcessMock(null)]) extends WorkerStarter {
			/** @var Process[] */
			private array $processes;

			public function __construct(IBinaryFinder $binaryFinder, ProcessManager $processManager, array $processes) {
				parent::__construct($binaryFinder, $processManager);
				$this->processes = $processes;
			}

			protected function createProcess(array $command): Process {
				return array_shift($this->processes);
			}
		};

		$workerStarter->startWorkers(3);
	}
}
