<?php

declare(strict_types=1);
/** SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Worker\LocalWorkerRegistry;
use OCA\Libresign\Service\Worker\WorkerConfiguration;
use OCA\Libresign\Service\Worker\WorkerHealthService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WorkerHealthServiceTest extends TestCase {
	public function testUnavailableQueueDoesNotStartWorkers(): void {
		$config = $this->createMock(WorkerConfiguration::class);
		$config->method('isAsyncLocalEnabled')->willReturn(true);
		$registry = $this->createMock(LocalWorkerRegistry::class);
		$registry->method('ensureCapacity')->willThrowException(new \RuntimeException('Query failed'));
		$service = new WorkerHealthService($config, $registry, $this->createMock(LoggerInterface::class));
		$this->assertFalse($service->ensureWorkerRunning());
	}
	public function testStartsOnlyWhenThereAreJobs(): void {
		$config = $this->createMock(WorkerConfiguration::class);
		$config->method('isAsyncLocalEnabled')->willReturn(true);
		$config->method('getDesiredWorkerCount')->willReturn(4);
		$registry = $this->createMock(LocalWorkerRegistry::class);
		$registry->expects($this->once())->method('ensureCapacity')->with(4)->willReturn(true);
		$service = new WorkerHealthService($config, $registry, $this->createMock(LoggerInterface::class));
		$this->assertTrue($service->ensureWorkerRunning());
	}
	public function testExternalModeNeverStartsLocalWorkers(): void {
		$config = $this->createMock(WorkerConfiguration::class);
		$config->method('isAsyncLocalEnabled')->willReturn(false);
		$registry = $this->createMock(LocalWorkerRegistry::class);
		$registry->expects($this->never())->method('ensureCapacity');
		$service = new WorkerHealthService($config, $registry, $this->createMock(LoggerInterface::class));
		$this->assertFalse($service->ensureWorkerRunning());
	}
}
