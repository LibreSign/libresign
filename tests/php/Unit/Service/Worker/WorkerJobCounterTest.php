<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Worker\WorkerConfiguration;
use OCA\Libresign\Service\Worker\WorkerJobCounter;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class WorkerJobCounterTest extends TestCase {
	public function testDoesNotReadQueueWithoutVacancies(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->never())->method('getQueryBuilder');
		$counter = new WorkerJobCounter($db, $this->createMock(WorkerConfiguration::class));
		$this->assertSame(0, $counter->countEligibleJobs(0));
		$this->assertSame(0, $counter->countEligibleJobs(-1));
	}

	public function testDatabaseQueryFailureDoesNotBecomeAnEmptyQueue(): void {
		$db = $this->createMock(IDBConnection::class);
		$db->method('getQueryBuilder')->willThrowException(new \RuntimeException('Database unavailable'));
		$this->expectException(\RuntimeException::class);
		(new WorkerJobCounter($db, $this->createMock(WorkerConfiguration::class)))->countEligibleJobs(2);
	}
}
