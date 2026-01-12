<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\WorkerHealthService;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use OCP\IBinaryFinder;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class WorkerHealthServiceTest extends TestCase {
	private IJobList&MockObject $jobList;
	private IAppConfig&MockObject $appConfig;
	private ITimeFactory&MockObject $timeFactory;
	private IBinaryFinder&MockObject $binaryFinder;
	private LoggerInterface&MockObject $logger;

	public function setUp(): void {
		parent::setUp();
		$this->jobList = $this->createMock(IJobList::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->binaryFinder = $this->createMock(IBinaryFinder::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function makeService(): WorkerHealthService {
		return new WorkerHealthService(
			$this->jobList,
			$this->appConfig,
			$this->timeFactory,
			$this->binaryFinder,
			$this->logger,
		);
	}

	public function testIsAsyncLocalEnabledFalseWhenNotAsync(): void {
		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with(Application::APP_ID, 'signing_mode', 'async')
			->willReturn('sync');

		$service = $this->makeService();
		$this->assertFalse($service->isAsyncLocalEnabled());
	}

	public function testIsAsyncLocalEnabledFalseWhenWorkerNotLocal(): void {
		$this->appConfig->expects($this->exactly(2))
			->method('getValueString')
			->willReturnMap([
				[Application::APP_ID, 'signing_mode', 'async', 'async'],
				[Application::APP_ID, 'worker_type', 'local', 'remote'],
			]);

		$service = $this->makeService();
		$this->assertFalse($service->isAsyncLocalEnabled());
	}

	public function testIsAsyncLocalEnabledTrueWhenAsyncLocal(): void {
		$this->appConfig->expects($this->exactly(2))
			->method('getValueString')
			->willReturnMap([
				[Application::APP_ID, 'signing_mode', 'async', 'async'],
				[Application::APP_ID, 'worker_type', 'local', 'local'],
			]);

		$service = $this->makeService();
		$this->assertTrue($service->isAsyncLocalEnabled());
	}

	public function testEnsureWorkerRunningReturnsFalseWhenAsyncDisabled(): void {
		$service = $this->getMockBuilder(WorkerHealthService::class)
			->setConstructorArgs([
				$this->jobList,
				$this->appConfig,
				$this->timeFactory,
				$this->binaryFinder,
				$this->logger,
			])
			->onlyMethods(['isAsyncLocalEnabled'])
			->getMock();

		$service->method('isAsyncLocalEnabled')->willReturn(false);

		$this->assertFalse($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningNoStartWhenEnoughWorkers(): void {
		// async local enabled
		$service = $this->getMockBuilder(WorkerHealthService::class)
			->setConstructorArgs([
				$this->jobList,
				$this->appConfig,
				$this->timeFactory,
				$this->binaryFinder,
				$this->logger,
			])
			->onlyMethods(['isAsyncLocalEnabled', 'countRunningWorkers'])
			->getMock();

		$service->method('isAsyncLocalEnabled')->willReturn(true);

		// desired from config = 4, running = 4
		$this->appConfig->expects($this->once())
			->method('getValueInt')
			->with(Application::APP_ID, 'parallel_workers', 4)
			->willReturn(4);
		$service->method('countRunningWorkers')->willReturn(4);

		// Should not attempt to set last attempt
		$this->appConfig->expects($this->never())
			->method('setValueInt');

		$this->assertTrue($service->ensureWorkerRunning());
	}

	public function testEnsureWorkerRunningThrottlesStartAttempts(): void {
		// async local enabled
		$service = $this->getMockBuilder(WorkerHealthService::class)
			->setConstructorArgs([
				$this->jobList,
				$this->appConfig,
				$this->timeFactory,
				$this->binaryFinder,
				$this->logger,
			])
			->onlyMethods(['isAsyncLocalEnabled', 'countRunningWorkers'])
			->getMock();

		$service->method('isAsyncLocalEnabled')->willReturn(true);

		// desired = 2 (clamped from config), running = 0 -> needed 2
		$this->appConfig->expects($this->exactly(2))
			->method('getValueInt')
			->willReturnMap([
				[Application::APP_ID, 'parallel_workers', 4, 2],
				['libresign', 'worker_last_start_attempt', 0, 95],
			]);
		$service->method('countRunningWorkers')->willReturn(0);

		// Throttle: last attempt 95, now 100 -> 5s elapsed (< 10s)
		$this->timeFactory->expects($this->once())
			->method('getTime')
			->willReturn(100);

		// Due to throttling, we must not set last attempt nor start
		$this->appConfig->expects($this->never())
			->method('setValueInt');

		$this->assertTrue($service->ensureWorkerRunning());
	}
}
