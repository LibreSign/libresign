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
use OCA\Libresign\Service\Worker\WorkerStarter;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class WorkerHealthServiceTest extends TestCase {
	private WorkerConfiguration&MockObject $config;
	private WorkerCounter&MockObject $counter;
	private StartThrottlePolicy&MockObject $throttle;
	private WorkerStarter&MockObject $starter;
	private LoggerInterface&MockObject $logger;

	public function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(WorkerConfiguration::class);
		$this->counter = $this->createMock(WorkerCounter::class);
		$this->throttle = $this->createMock(StartThrottlePolicy::class);
		$this->starter = $this->createMock(WorkerStarter::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function makeService(): WorkerHealthService {
		return new WorkerHealthService(
			$this->config,
			$this->counter,
			$this->throttle,
			$this->starter,
			$this->logger,
		);
	}

	public function testEnsureWorkerRunningReturnsFalseWhenAsyncDisabled(): void {
		$this->config->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(false);

		$service = $this->makeService();
		$this->assertFalse($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningNoStartWhenEnoughWorkers(): void {
		$this->config->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->config->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(4);

		$this->counter->expects($this->once())
			->method('countRunning')
			->willReturn(4);

		$this->throttle->expects($this->never())
			->method('isThrottled');

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningRespectThrottlePolicy(): void {
		$this->config->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->config->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(4);

		$this->counter->expects($this->once())
			->method('countRunning')
			->willReturn(0);

		$this->throttle->expects($this->once())
			->method('isThrottled')
			->willReturn(true);

		$this->throttle->expects($this->never())
			->method('recordAttempt');

		$this->starter->expects($this->never())
			->method('startWorkers');

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningStartsWhenNotThrottled(): void {
		$this->config->expects($this->once())
			->method('isAsyncLocalEnabled')
			->willReturn(true);

		$this->config->expects($this->once())
			->method('getDesiredWorkerCount')
			->willReturn(4);

		$this->counter->expects($this->once())
			->method('countRunning')
			->willReturn(2);

		$this->throttle->expects($this->once())
			->method('isThrottled')
			->willReturn(false);

		$this->throttle->expects($this->once())
			->method('recordAttempt');

		$this->starter->expects($this->once())
			->method('startWorkers')
			->with(2);

		$service = $this->makeService();
		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningHandlesExceptions(): void {
		$this->config->expects($this->once())
			->method('isAsyncLocalEnabled')
			->will($this->throwException(new \RuntimeException('Config error')));

		$this->logger->expects($this->once())
			->method('error')
			->with($this->stringContains('Failed to ensure worker is running'));

		$service = $this->makeService();
		$this->assertFalse($service->ensureWorkerRunning());
	}
}
