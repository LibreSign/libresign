<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Worker\LocalWorkerProcess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocalWorkerProcessTest extends TestCase {
	/** @return array<string, array{int}> */
	public static function missingPids(): array {
		return [
			'zero' => [0],
			'negative' => [-1],
		];
	}

	#[DataProvider('missingPids')]
	public function testInvalidPidIsNeverConsideredAlive(int $pid): void {
		$processes = new LocalWorkerProcess();
		$this->assertNull($processes->startTime($pid));
		$this->assertFalse($processes->isRunning($pid, '123'));
	}

	public function testPidReuseAndUnrelatedProcessesCannotCountAsWorkers(): void {
		if (PHP_OS_FAMILY !== 'Linux') {
			$this->markTestSkipped('Linux process identity');
		}
		$processes = new LocalWorkerProcess();
		$pid = getmypid();
		$startTime = $processes->startTime($pid);
		$this->assertNotNull($startTime);
		$this->assertFalse($processes->isRunning($pid, 'reused-process-start'));
		$this->assertFalse($processes->isRunning($pid, $startTime), 'PHPUnit itself is not an OCC signing worker');
	}
	/** @return array<string, array{string, bool}> */
	public static function workerCommandCases(): array {
		return [
			'exact Nextcloud worker' => ["php\0/srv/nextcloud/occ\0background-job:worker\0", true],
			'another Nextcloud instance' => ["php\0/srv/unrelated/occ\0background-job:worker\0", false],
			'unrelated occ command' => ["php\0/srv/nextcloud/occ\0background-job:list\0", false],
			'unrelated executable' => ["php\0/srv/nextcloud/notocc\0background-job:worker\0", false],
		];
	}

	#[DataProvider('workerCommandCases')]
	public function testOnlyRecognizesTheExpectedWorkerProcess(string $cmdline, bool $expected): void {
		if (PHP_OS_FAMILY !== 'Linux') {
			$this->markTestSkipped('Linux procfs fixture');
		}
		$procRoot = sys_get_temp_dir() . '/libresign-proc-' . bin2hex(random_bytes(12));
		$pid = 1234;
		mkdir($procRoot, 0700);
		mkdir($procRoot . '/' . $pid, 0700);
		$fields = array_fill(0, 21, '0');
		$fields[0] = 'S';
		$fields[19] = '12345678';
		file_put_contents($procRoot . '/' . $pid . '/stat', $pid . ' (php worker (occ)) ' . implode(' ', $fields));
		file_put_contents($procRoot . '/' . $pid . '/cmdline', $cmdline);
		try {
			$processes = new LocalWorkerProcess($procRoot, '/srv/nextcloud/occ');
			$this->assertSame('12345678', $processes->startTime($pid));
			$this->assertSame($expected, $processes->isRunning($pid, '12345678'));
			$this->assertFalse($processes->isRunning($pid, 'reused-process-birth'));
		} finally {
			@unlink($procRoot . '/' . $pid . '/stat');
			@unlink($procRoot . '/' . $pid . '/cmdline');
			@rmdir($procRoot . '/' . $pid);
			@rmdir($procRoot);
		}
	}

}
