<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Worker\LocalWorkerCoordinator;
use OCA\Libresign\Service\Worker\LocalWorkerProcess;
use OCA\Libresign\Service\Worker\LocalWorkerStateStore;
use OCA\Libresign\Service\Worker\WorkerJobCounter;
use OCA\Libresign\Service\Worker\WorkerStarter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LocalWorkerCoordinatorTest extends TestCase {
	private string $directory;

	protected function setUp(): void {
		if (PHP_OS_FAMILY !== 'Linux') {
			$this->markTestSkipped('Local process coordination requires Linux');
		}
		$this->directory = sys_get_temp_dir() . '/libresign-worker-test-' . bin2hex(random_bytes(12));
	}

	protected function tearDown(): void {
		foreach (glob($this->directory . '/*') ?: [] as $path) {
			@unlink($path);
		}
		@chmod($this->directory, 0700);
		@rmdir($this->directory);
	}

	private function coordinator(WorkerStarter $starter, WorkerJobCounter $counter, ?LocalWorkerProcess $processes = null): LocalWorkerCoordinator {
		$processes ??= $this->createMock(LocalWorkerProcess::class);
		$processes->method('isSupported')->willReturn(true);
		$processes->method('startTime')->willReturn('process-birth-time');
		return new LocalWorkerCoordinator($starter, $counter, $processes, new LocalWorkerStateStore($this->directory), $this->createMock(LoggerInterface::class));
	}

	/** @return array<string, array{int, int, int}> */
	public static function capacityCases(): array {
		return [
			'no capacity' => [0, 5, 0],
			'no jobs' => [3, 0, 0],
			'only one job' => [4, 1, 1],
			'capacity limits starts' => [2, 9, 2],
			'negative capacity' => [-1, 8, 0],
		];
	}

	#[DataProvider('capacityCases')]
	public function testStartsOnlyNeededWorkers(int $capacity, int $queued, int $expectedStarts): void {
		$starts = 0;
		$starter = $this->createMock(WorkerStarter::class);
		$starter->method('startWorker')->willReturnCallback(static function () use (&$starts): int {
			return 6000 + ++$starts;
		});
		$counter = $this->createMock(WorkerJobCounter::class);
		$counter->method('countEligibleJobs')->willReturn(min($capacity, $queued));
		$this->assertTrue($this->coordinator($starter, $counter)->ensureCapacity($capacity));
		$this->assertSame($expectedStarts, $starts);
	}

	public function testSecondRequestDoesNotExceedCapacity(): void {
		$starts = 0;
		$starter = $this->createMock(WorkerStarter::class);
		$starter->method('startWorker')->willReturnCallback(static function () use (&$starts): int {
			return 6100 + ++$starts;
		});
		$processes = $this->createMock(LocalWorkerProcess::class);
		$processes->method('isRunning')->willReturn(true);
		$counter = $this->createMock(WorkerJobCounter::class);
		$counter->method('countEligibleJobs')->willReturn(20);
		$registry = $this->coordinator($starter, $counter, $processes);
		$this->assertTrue($registry->ensureCapacity(1));
		$this->assertTrue($registry->ensureCapacity(1));
		$this->assertSame(1, $starts);
	}

	public function testConcurrentRequestsLaunchOneWorker(): void {
		if (!function_exists('pcntl_fork')) {
			$this->markTestSkipped('Requires POSIX process isolation');
		}
		$marker = $this->directory . '-started';
		$release = $this->directory . '-release';
		$starter = $this->createMock(WorkerStarter::class);
		$starter->method('startWorker')->willReturnCallback(static function () use ($marker, $release): int {
			file_put_contents($marker, "started\n", FILE_APPEND);
			$deadline = microtime(true) + 4;
			while (!is_file($release) && microtime(true) < $deadline) {
				usleep(10000);
			}
			if (!is_file($release)) {
				throw new \RuntimeException('Concurrent request did not finish');
			}
			return 6551;
		});
		$counter = $this->createMock(WorkerJobCounter::class);
		$counter->method('countEligibleJobs')->willReturn(1);
		$child = pcntl_fork();
		$this->assertNotSame(-1, $child, 'Could not start parallel request');
		if ($child === 0) {
			try {
				$this->coordinator($starter, $counter)->ensureCapacity(1);
				exit(0);
			} catch (\Exception) {
				exit(1);
			}
		}
		try {
			$deadline = microtime(true) + 3;
			while (!is_file($marker) && microtime(true) < $deadline) {
				usleep(10000);
			}
			$this->assertFileExists($marker);
			$this->assertTrue($this->coordinator($starter, $counter)->ensureCapacity(1));
			file_put_contents($release, 'ok');
			pcntl_waitpid($child, $status);
			$this->assertTrue(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0);
			$this->assertCount(1, file($marker));
		} finally {
			file_put_contents($release, 'ok');
			@unlink($marker);
			@unlink($release);
		}
	}

	public function testRestrictedProcfsLeavesSigningWorkToNextcloudCron(): void {
		$procRoot = sys_get_temp_dir() . '/libresign-restricted-proc-' . bin2hex(random_bytes(12));
		mkdir($procRoot, 0700);
		try {
			$starter = $this->createMock(WorkerStarter::class);
			$starter->expects($this->never())->method('startWorker');
			$counter = $this->createMock(WorkerJobCounter::class);
			$counter->expects($this->never())->method('countEligibleJobs');
			$logger = $this->createMock(LoggerInterface::class);
			$logger->expects($this->once())->method('warning')->with($this->stringContains('procfs'));
			$service = new LocalWorkerCoordinator(
				$starter, $counter, new LocalWorkerProcess($procRoot),
				new LocalWorkerStateStore($this->directory), $logger,
			);
			$this->assertFalse($service->ensureCapacity(2));
		} finally {
			@rmdir($procRoot);
		}
	}

	public function testWorkerStartupFailureIsVisible(): void {
		$starter = $this->createMock(WorkerStarter::class);
		$starter->method('startWorker')->willThrowException(new \RuntimeException('PHP is unavailable'));
		$counter = $this->createMock(WorkerJobCounter::class);
		$counter->method('countEligibleJobs')->willReturn(1);
		$this->assertFalse($this->coordinator($starter, $counter)->ensureCapacity(1));
	}

	public function testQueryErrorDoesNotLookLikeEmptyQueue(): void {
		$counter = $this->createMock(WorkerJobCounter::class);
		$counter->method('countEligibleJobs')->willThrowException(new \RuntimeException('Queue unavailable'));
		$this->expectException(\RuntimeException::class);
		$this->coordinator($this->createMock(WorkerStarter::class), $counter)->ensureCapacity(1);
	}

}
