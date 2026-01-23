<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCA\Libresign\Service\Worker\WorkerJobCounter;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class WorkerJobCounterTest extends TestCase {
	private IJobList&MockObject $jobList;
	private LoggerInterface&MockObject $logger;

	public function setUp(): void {
		parent::setUp();
		$this->jobList = $this->createMock(IJobList::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function makeCounter(): WorkerJobCounter {
		return new WorkerJobCounter($this->jobList, $this->logger);
	}

	public function testCountPendingJobsReturnsZeroWhenNoJobs(): void {
		$this->jobList->expects($this->once())
			->method('countByClass')
			->willReturn([]);

		$counter = $this->makeCounter();
		$this->assertSame(0, $counter->countPendingJobs());
	}

	public function testCountPendingJobsCountsSignFileJobs(): void {
		$this->jobList->expects($this->once())
			->method('countByClass')
			->willReturn([
				['class' => SignFileJob::class, 'count' => 5],
				['class' => 'SomeOtherJob', 'count' => 10],
			]);

		$counter = $this->makeCounter();
		$this->assertSame(5, $counter->countPendingJobs());
	}

	public function testCountPendingJobsCountsSignSingleFileJobs(): void {
		$this->jobList->expects($this->once())
			->method('countByClass')
			->willReturn([
				['class' => SignSingleFileJob::class, 'count' => 3],
				['class' => 'AnotherJob', 'count' => 7],
			]);

		$counter = $this->makeCounter();
		$this->assertSame(3, $counter->countPendingJobs());
	}

	public function testCountPendingJobsSumsBothJobTypes(): void {
		$this->jobList->expects($this->once())
			->method('countByClass')
			->willReturn([
				['class' => SignFileJob::class, 'count' => 5],
				['class' => SignSingleFileJob::class, 'count' => 3],
				['class' => 'UnrelatedJob', 'count' => 100],
			]);

		$counter = $this->makeCounter();
		$this->assertSame(8, $counter->countPendingJobs());
	}

	public function testCountPendingJobsIgnoresOtherJobTypes(): void {
		$this->jobList->expects($this->once())
			->method('countByClass')
			->willReturn([
				['class' => 'SomeJob', 'count' => 50],
				['class' => 'AnotherJob', 'count' => 30],
			]);

		$counter = $this->makeCounter();
		$this->assertSame(0, $counter->countPendingJobs());
	}

	public function testCountPendingJobsReturnsZeroOnException(): void {
		$this->jobList->expects($this->once())
			->method('countByClass')
			->will($this->throwException(new \RuntimeException('Database error')));

		$this->logger->expects($this->once())
			->method('debug')
			->with(
				$this->stringContains('Failed to count pending jobs'),
				$this->arrayHasKey('error')
			);

		$counter = $this->makeCounter();
		$this->assertSame(0, $counter->countPendingJobs());
	}

	public function testCountPendingJobsHandlesEmptyCountArray(): void {
		$this->jobList->expects($this->once())
			->method('countByClass')
			->willReturn([]);

		$counter = $this->makeCounter();
		$result = $counter->countPendingJobs();

		$this->assertIsInt($result);
		$this->assertSame(0, $result);
	}

	public function testCountPendingJobsHandlesLargeNumbers(): void {
		$this->jobList->expects($this->once())
			->method('countByClass')
			->willReturn([
				['class' => SignFileJob::class, 'count' => 1000],
				['class' => SignSingleFileJob::class, 'count' => 2000],
			]);

		$counter = $this->makeCounter();
		$this->assertSame(3000, $counter->countPendingJobs());
	}
}
