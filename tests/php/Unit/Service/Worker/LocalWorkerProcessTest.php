<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\BackgroundJob\DeliverNotification;
use OCA\Libresign\BackgroundJob\SignFileJob;
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

	public function testUnreadableProcessInformationDisablesLocalWorkerMode(): void {
		if (PHP_OS_FAMILY !== 'Linux') {
			$this->markTestSkipped('Linux process inspection');
		}
		$root = sys_get_temp_dir() . '/libresign-proc-support-' . bin2hex(random_bytes(12));
		mkdir($root, 0700);
		try {
			// Existing procfs mount is not sufficient when the PHP runtime is
			// denied access to its contents (e.g. open_basedir restrictions).
			$this->assertFalse((new LocalWorkerProcess($root))->isSupported());
			mkdir($root . '/self', 0700);
			file_put_contents($root . '/self/stat', 'readable');
			$this->assertFalse((new LocalWorkerProcess($root))->isSupported());
			file_put_contents($root . '/self/cmdline', 'readable');
			$this->assertTrue((new LocalWorkerProcess($root))->isSupported());
		} finally {
			@unlink($root . '/self/cmdline');
			@unlink($root . '/self/stat');
			@rmdir($root . '/self');
			@rmdir($root);
		}
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
			'worker with CLI options' => ["php\0/srv/nextcloud/occ\0background-job:worker\0--interval=0\0", true],
			'another Nextcloud instance' => ["php\0/srv/unrelated/occ\0background-job:worker\0", false],
			'unrelated occ command' => ["php\0/srv/nextcloud/occ\0background-job:list\0", false],
			'unrelated executable' => ["php\0/srv/nextcloud/notocc\0background-job:worker\0", false],
			'OCC path only appears as an unrelated argument' => ["php\0-r\0echo\0/srv/nextcloud/occ\0background-job:worker\0", false],
			'worker subcommand appears after another OCC command' => ["php\0/srv/nextcloud/occ\0background-job:list\0background-job:worker\0", false],
			'notification class and flags are identified' => [
				"php\0/srv/nextcloud/occ\0background-job:worker\0" . DeliverNotification::class . "\0--stop_after=30m\0--interval=0\0",
				true, [DeliverNotification::class],
			],
			'signing class cannot count as a notification worker' => [
				"php\0/srv/nextcloud/occ\0background-job:worker\0" . SignFileJob::class . "\0--stop_after=30m\0--interval=0\0",
				false, [DeliverNotification::class],
			],
			'notification class cannot count as a signing worker' => [
				"php\0/srv/nextcloud/occ\0background-job:worker\0" . DeliverNotification::class . "\0--stop_after=30m\0--interval=0\0",
				false, [SignFileJob::class],
			],
			'an unexpected extra argument invalidates pool identity' => [
				"php\0/srv/nextcloud/occ\0background-job:worker\0" . DeliverNotification::class . "\0--stop_after=30m\0--interval=0\0--unknown\0",
				false, [DeliverNotification::class],
			],
		];
	}

	#[DataProvider('workerCommandCases')]
	public function testOnlyRecognizesTheExpectedWorkerProcess(string $cmdline, bool $expected, array $jobClasses = []): void {
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
			$this->assertSame($expected, $processes->isRunning($pid, '12345678', $jobClasses));
			$this->assertFalse($processes->isRunning($pid, 'reused-process-birth'));
		} finally {
			@unlink($procRoot . '/' . $pid . '/stat');
			@unlink($procRoot . '/' . $pid . '/cmdline');
			@rmdir($procRoot . '/' . $pid);
			@rmdir($procRoot);
		}
	}

}
