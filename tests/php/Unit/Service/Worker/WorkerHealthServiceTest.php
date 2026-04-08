<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Process\ProcessManager;
use OCA\Libresign\Service\Worker\StartThrottlePolicy;
use OCA\Libresign\Service\Worker\WorkerConfiguration;
use OCA\Libresign\Service\Worker\WorkerHealthService;
use OCA\Libresign\Service\Worker\WorkerJobCounter;
use OCA\Libresign\Service\Worker\WorkerStarter;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class WorkerHealthServiceTest extends TestCase {
	private WorkerConfiguration&MockObject $workerConfiguration;
	private ProcessManager&MockObject $processManager;
	private WorkerJobCounter&MockObject $workerJobCounter;
	private StartThrottlePolicy&MockObject $startThrottlePolicy;
	private WorkerStarter&MockObject $workerStarter;
	private LoggerInterface&MockObject $logger;

	public function setUp(): void {
		parent::setUp();
		$this->workerConfiguration = $this->createMock(WorkerConfiguration::class);
		$this->processManager = $this->createMock(ProcessManager::class);
		$this->workerJobCounter = $this->createMock(WorkerJobCounter::class);
		$this->startThrottlePolicy = $this->createMock(StartThrottlePolicy::class);
		$this->workerStarter = $this->createMock(WorkerStarter::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function makeService(): WorkerHealthService {
		return new WorkerHealthService(
			$this->workerConfiguration,
			$this->processManager,
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

	#[DataProvider('providerNoStartScenarios')]
	public function testEnsureWorkerRunningDoesNotStartWorkers(int $desired, int $running, int $pendingJobs): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn($desired);

		$this->processManager->expects($this->once())
			->method('countRunning')
			->with('worker')
			->willReturn($running);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn($pendingJobs);

		$this->startThrottlePolicy->expects($this->never())
			->method('isThrottled');

		$this->workerStarter->expects($this->never())
			->method('startWorkers');

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public static function providerNoStartScenarios(): array {
		return [
			'no pending jobs' => [4, 0, 0],
			'enough workers already running' => [4, 4, 10],
			'running workers exceeds desired' => [2, 5, 10],
			'desired workers is zero' => [0, 0, 10],
		];
	}

	public function testEnsureWorkerRunningRespectsThrottlePolicy(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(4);

		$this->processManager->expects($this->once())
			->method('countRunning')
			->with('worker')
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

	#[DataProvider('providerStartWorkerScenarios')]
	public function testEnsureWorkerRunningStartsExpectedNumberOfWorkers(int $desired, int $running, int $pendingJobs, int $expectedStarts): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn($desired);

		$this->processManager->expects($this->once())
			->method('countRunning')
			->with('worker')
			->willReturn($running);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn($pendingJobs);

		$this->startThrottlePolicy->expects($this->once())
			->method('isThrottled')
			->willReturn(false);

		$this->startThrottlePolicy->expects($this->once())
			->method('recordAttempt');

		$this->workerStarter->expects($this->once())
			->method('startWorkers')
			->with($expectedStarts);

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public static function providerStartWorkerScenarios(): array {
		return [
			'fills remaining desired capacity' => [4, 2, 10, 2],
			'limited by pending jobs with no running' => [10, 0, 3, 3],
			'limited by pending jobs with running workers' => [10, 3, 5, 5],
			'pending jobs smaller than desired gap' => [10, 1, 2, 2],
			'single pending job starts one worker' => [4, 0, 1, 1],
		];
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

	public function testEnsureWorkerRunningReturnsFalseWhenWorkerStarterFails(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->workerConfiguration->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(2);

		$this->processManager->expects($this->once())
			->method('countRunning')
			->with('worker')
			->willReturn(0);

		$this->workerJobCounter->expects($this->once())
			->method('countPendingJobs')
			->willReturn(2);

		$this->startThrottlePolicy->expects($this->once())
			->method('isThrottled')
			->willReturn(false);

		$this->startThrottlePolicy->expects($this->once())
			->method('recordAttempt');

		$this->workerStarter->expects($this->once())
			->method('startWorkers')
			->with(2)
			->will($this->throwException(new \RuntimeException('process launch failed')));

		$this->logger->expects($this->once())
			->method('error')
			->with(
				$this->stringContains('Failed to ensure worker is running'),
				$this->arrayHasKey('exception')
			);

		$service = $this->makeService();
		$this->assertFalse($service->ensureWorkerRunning());
	}

	public function testIsAsyncLocalEnabledDelegatesToConfiguration(): void {
		$this->workerConfiguration->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$service = $this->makeService();
		$this->assertTrue($service->isAsyncLocalEnabled());
	}
}
