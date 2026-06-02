<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Worker\StartThrottlePolicy;
use OCA\Libresign\Service\Worker\WorkerConfiguration;
use OCA\Libresign\Service\Worker\WorkerCounter;
use OCA\Libresign\Service\Worker\WorkerHealthService;
use OCA\Libresign\Service\Worker\WorkerJobCounter;
use OCA\Libresign\Service\Worker\WorkerStarter;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class WorkerHealthServiceTest extends TestCase {
	private WorkerConfiguration&MockObject $workerConfiguration;
	private WorkerCounter&MockObject $workerCounter;
	private WorkerJobCounter&MockObject $workerJobCounter;
	private StartThrottlePolicy&MockObject $startThrottlePolicy;
	private WorkerStarter&MockObject $workerStarter;
	private LoggerInterface&MockObject $logger;

	public function setUp(): void {
		parent::setUp();
		$this->workerConfiguration = $this->createMock(WorkerConfiguration::class);
		$this->workerCounter = $this->createMock(WorkerCounter::class);
		$this->workerJobCounter = $this->createMock(WorkerJobCounter::class);
		$this->startThrottlePolicy = $this->createMock(StartThrottlePolicy::class);
		$this->workerStarter = $this->createMock(WorkerStarter::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function makeService(): WorkerHealthService {
		return new WorkerHealthService(
			$this->workerConfiguration,
			$this->workerCounter,
			$this->workerJobCounter,
			$this->startThrottlePolicy,
			$this->workerStarter,
			$this->logger
		);
	}

	public function testEnsureWorkerRunningReturnsFalseWhenAsyncDisabled(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(false);

		$service = $this->makeService();
		$this->assertFalse($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningNoStartWhenNoPendingJobs(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(4);

		$this->workerCounter->expects($this->once())
			->method('countRunning')
			->willReturn(0);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn(0);

		$this->startThrottlePolicy->expects($this->never())
			->method('isThrottled');

		$this->workerStarter->expects($this->never())
			->method('startWorkers');

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningNoStartWhenEnoughWorkers(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(4);

		$this->workerCounter->expects($this->once())
			->method('countRunning')
			->willReturn(4);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn(10);

		$this->startThrottlePolicy->expects($this->never())
			->method('isThrottled');

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningRespectThrottlePolicy(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(4);

		$this->workerCounter->expects($this->once())
			->method('countRunning')
			->willReturn(0);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn(5);

		$this->startThrottlePolicy->expects($this->once())
			->method('isThrottled')
			->willReturn(true);

		$this->startThrottlePolicy->expects($this->never())
			->method('recordAttempt');

		$this->workerStarter->expects($this->never())
			->method('startWorkers');

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningStartsWhenNotThrottled(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(4);

		$this->workerCounter->expects($this->once())
			->method('countRunning')
			->willReturn(2);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn(10);

		$this->startThrottlePolicy->expects($this->once())
			->method('isThrottled')
			->willReturn(false);

		$this->startThrottlePolicy->expects($this->once())
			->method('recordAttempt');

		$this->workerStarter->expects($this->once())
			->method('startWorkers')
			->with(2);

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningStartsOnlyNeededWorkersBasedOnPendingJobs(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(10);

		$this->workerCounter->expects($this->once())
			->method('countRunning')
			->willReturn(0);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn(3);

		$this->startThrottlePolicy->expects($this->once())
			->method('isThrottled')
			->willReturn(false);

		$this->startThrottlePolicy->expects($this->once())
			->method('recordAttempt');

		// Should only start 3 workers because there are only 3 pending jobs
		$this->workerStarter->expects($this->once())
			->method('startWorkers')
			->with(3);

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningLimitsWorkersByPendingJobsAndRunningWorkers(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(10);

		$this->workerCounter->expects($this->once())
			->method('countRunning')
			->willReturn(3);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn(5);

		$this->startThrottlePolicy->expects($this->once())
			->method('isThrottled')
			->willReturn(false);

		$this->startThrottlePolicy->expects($this->once())
			->method('recordAttempt');

		// Pending jobs = 5, Running = 3, Desired = 10
		// Should start min(5, 10-3) = min(5, 7) = 5 workers
		$this->workerStarter->expects($this->once())
			->method('startWorkers')
			->with(5);

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningStartsFewerWorkersWhenLessPendingJobsThanGap(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(10);

		$this->workerCounter->expects($this->once())
			->method('countRunning')
			->willReturn(1);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn(2);

		$this->startThrottlePolicy->expects($this->once())
			->method('isThrottled')
			->willReturn(false);

		$this->startThrottlePolicy->expects($this->once())
			->method('recordAttempt');

		// Pending jobs = 2, Running = 1, Desired = 10
		// Gap would be 9, but only 2 jobs pending
		// Should start min(2, 9) = 2 workers
		$this->workerStarter->expects($this->once())
			->method('startWorkers')
			->with(2);

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningStartsOneWorkerWhenOnlyOneJobPending(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(4);

		$this->workerCounter->expects($this->once())
			->method('countRunning')
			->willReturn(0);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn(1);

		$this->startThrottlePolicy->expects($this->once())
			->method('isThrottled')
			->willReturn(false);

		$this->startThrottlePolicy->expects($this->once())
			->method('recordAttempt');

		// Should only start 1 worker for 1 job
		$this->workerStarter->expects($this->once())
			->method('startWorkers')
			->with(1);

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningHandlesExceptions(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->will($this->throwException(new \RuntimeException('Config error')));

		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('Failed to ensure worker is running'));

		$service = $this->makeService();
		$this->assertFalse($service->ensureWorkerRunning());
	}
}
