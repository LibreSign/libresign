<?php

declare(strict_types=1);
/** SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\Service\Worker\WorkerConfiguration;
use OCA\Libresign\Service\Worker\WorkerStarter;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
use OCP\IBinaryFinder;
use PHPUnit\Framework\TestCase;

class WorkerStarterTest extends TestCase {
	public function testCannotStartWithoutCliPhp(): void {
		$finder = $this->createMock(IBinaryFinder::class);
		$finder->method('findBinaryPath')->with('php')->willReturn(false);
		$starter = new WorkerStarter($finder, $this->createMock(WorkerConfiguration::class));
		$this->expectException(\RuntimeException::class);
		$starter->startWorker();
	}
	public function testUsesArgumentArrayAndExitsWhenQueueDrains(): void {
		$finder = $this->createMock(IBinaryFinder::class);
		$finder->method('findBinaryPath')->with('php')->willReturn('/usr/bin/php');
		$config = $this->createMock(WorkerConfiguration::class);
		$config->method('getSigningJobClasses')->willReturn([SignFileJob::class]);
		$process = $this->createMock(Process::class);
		$process->expects($this->once())->method('setOptions')->with(['create_new_console' => true]);
		$process->expects($this->once())->method('start');
		$process->method('getPid')->willReturn(10001);
		$starter = new class($finder, $config, $process) extends WorkerStarter {
			public array $command = [];
			public function __construct(
				IBinaryFinder $finder,
				WorkerConfiguration $config,
				private Process $process,
			) {
				parent::__construct($finder, $config);
			}
			protected function createProcess(array $command): Process {
				$this->command = $command;
				return $this->process;
			}
		};
		$this->assertSame(10001, $starter->startWorker());
		$this->assertSame([
			'/usr/bin/php',
			\OC::$SERVERROOT . '/occ',
			'background-job:worker',
			SignFileJob::class,
			'--stop_after=30m',
			'--interval=0',
		], $starter->command);
	}

}
