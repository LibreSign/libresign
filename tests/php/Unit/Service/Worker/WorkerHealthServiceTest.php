<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Worker\StartThrottlePolicy;
use OCA\Libresign\Service\Worker\WorkerConfiguration;
use OCA\Libresign\Service\Worker\WorkerHealthService;
use OCA\Libresign\Service\Worker\WorkerJobCounter;
use OCA\Libresign\Service\Worker\WorkerStarter;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class WorkerHealthServiceTest extends TestCase {
	private WorkerConfiguration&MockObject $workerConfiguration;
	private WorkerJobCounter&MockObject $workerJobCounter;
	private StartThrottlePolicy&MockObject $startThrottlePolicy;
	private WorkerStarter&MockObject $workerStarter;
	private LoggerInterface&MockObject $logger;

	public function setUp(): void {
		parent::setUp();
		$this->workerConfiguration = $this->createMock(WorkerConfiguration::class);
		$this->workerJobCounter = $this->createMock(WorkerJobCounter::class);
		$this->startThrottlePolicy = $this->createMock(StartThrottlePolicy::class);
		$this->workerStarter = $this->createMock(WorkerStarter::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function makeService(): WorkerHealthService {
		return new WorkerHealthService(
			$this->workerConfiguration,
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

	public function testEnsureWorkerRunningRespectThrottlePolicy(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

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

	public function testEnsureWorkerRunningStartsWorkersWhenNotThrottledAndPendingJobs(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(4);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn(2);

		$this->startThrottlePolicy->expects($this->once())
			->method('isThrottled')
			->willReturn(false);

		$this->startThrottlePolicy->expects($this->once())
			->method('recordAttempt');

		// Should start min(2 pending, 4 desired) = 2 workers
		$this->workerStarter->expects($this->once())
			->method('startWorkers')
			->with(2);

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningLimitsWorkersByDesiredCount(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(3);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn(10);

		$this->startThrottlePolicy->expects($this->once())
			->method('isThrottled')
			->willReturn(false);

		$this->startThrottlePolicy->expects($this->once())
			->method('recordAttempt');

		// Should start min(10 pending, 3 desired) = 3 workers
		$this->workerStarter->expects($this->once())
			->method('startWorkers')
			->with(3);

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningStartsOneWorkerForOnePendingJob(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(4);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn(1);

		$this->startThrottlePolicy->expects($this->once())
			->method('isThrottled')
			->willReturn(false);

		$this->startThrottlePolicy->expects($this->once())
			->method('recordAttempt');

		// Should start min(1 pending, 4 desired) = 1 worker
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
