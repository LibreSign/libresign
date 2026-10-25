<?php

declare(strict_types=1);
/** SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Worker\LocalWorkerRegistry;
use OCA\Libresign\Service\Worker\WorkerJobCounter;
use OCA\Libresign\Service\Worker\WorkerStarter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LocalWorkerRegistryTest extends TestCase {
	private string $directory;

	protected function setUp(): void {
		if (PHP_OS_FAMILY !== 'Linux') {
			$this->markTestSkipped('Linux process lock test');
		}
		$this->directory = sys_get_temp_dir() . '/libresign-worker-unit-' . bin2hex(random_bytes(12));
	}

	protected function tearDown(): void {
		foreach (['workers.json', 'lock'] as $name) {
			@unlink($this->directory . '/' . $name);
		}
		@rmdir($this->directory);
	}

	private function registry(WorkerStarter $starter, WorkerJobCounter $counter): LocalWorkerRegistry {
		$dir = $this->directory;
		return new class($starter, $counter, $this->createMock(LoggerInterface::class), $dir) extends LocalWorkerRegistry {
			public function __construct(
				WorkerStarter $starter,
				WorkerJobCounter $counter,
				LoggerInterface $logger,
				private string $directory,
			) {
				parent::__construct($starter, $counter, $logger);
			}
			protected function registryDirectory(): string {
				return $this->directory;
			}
			protected function pidStartTime(int $pid): ?string {
				return $pid > 0 ? '12345' : null;
			}
			protected function ownsRunningWorker(int $pid, string $started): bool {
				return $pid === 6789 && $started === '12345';
			}
		};
	}

	public function testRejectsWorldWritableRegistry(): void {
		mkdir($this->directory, 0700);
		chmod($this->directory, 0777);
		$this->expectException(\RuntimeException::class);
		$this->registry($this->createMock(WorkerStarter::class), $this->createMock(WorkerJobCounter::class))->ensureCapacity(1);
	}

	public function testRejectsSymlinkLockBeforeOpeningIt(): void {
		mkdir($this->directory, 0700);
		$target = $this->directory . '/safe-target';
		file_put_contents($target, 'unchanged');
		symlink($target, $this->directory . '/lock');
		try {
			$this->expectException(\RuntimeException::class);
			$this->registry($this->createMock(WorkerStarter::class), $this->createMock(WorkerJobCounter::class))->ensureCapacity(1);
		} finally {
			$this->assertSame('unchanged', file_get_contents($target));
			unlink($target);
		}
	}

	public function testAtomicLocalLockPreventsConcurrentLaunch(): void {
		mkdir($this->directory, 0700);
		$handle = fopen($this->directory . '/lock', 'c+');
		flock($handle, LOCK_EX | LOCK_NB);
		$starter = $this->createMock(WorkerStarter::class);
		$starter->expects($this->never())->method('startWorker');
		$counter = $this->createMock(WorkerJobCounter::class);
		$counter->expects($this->never())->method('countEligibleJobs');
		$this->assertTrue($this->registry($starter, $counter)->ensureCapacity(4));
		flock($handle, LOCK_UN);
		fclose($handle);
	}

	public function testRespectsCapacityAndDoesNotCountQueueWhenFull(): void {
		$starter = $this->createMock(WorkerStarter::class);
		$starter->expects($this->once())->method('startWorker')->willReturn(6789);
		$counter = $this->createMock(WorkerJobCounter::class);
		$counter->expects($this->once())->method('countEligibleJobs')->willReturn(1000);
		$registry = $this->registry($starter, $counter);
		$this->assertTrue($registry->ensureCapacity(1));
		$this->assertTrue($registry->ensureCapacity(1));
		$this->assertCount(1, json_decode(file_get_contents($this->directory . '/workers.json'), true)['workers']);
	}

	public function testReusedPidWithDifferentStartTimeIsNotCounted(): void {
		mkdir($this->directory, 0700);
		file_put_contents($this->directory . '/workers.json', json_encode([
			'workers' => [['pid' => 6789, 'start' => 'old-process']],
			'lastAttempt' => 0,
		]));
		$starter = $this->createMock(WorkerStarter::class);
		$starter->expects($this->once())->method('startWorker')->willReturn(6789);
		$counter = $this->createMock(WorkerJobCounter::class);
		$counter->method('countEligibleJobs')->willReturn(1);
		$this->assertTrue($this->registry($starter, $counter)->ensureCapacity(1));
		$workers = json_decode(file_get_contents($this->directory . '/workers.json'), true)['workers'];
		$this->assertSame('12345', $workers[0]['start']);
	}
}
