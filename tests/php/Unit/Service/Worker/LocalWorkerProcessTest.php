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
}
