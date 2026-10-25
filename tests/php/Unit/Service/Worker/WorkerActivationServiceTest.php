<?php

declare(strict_types=1);
/** SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Worker;

use OCA\Libresign\Service\Worker\LocalWorkerCoordinator;
use OCA\Libresign\Service\Worker\WorkerActivationService;
use OCA\Libresign\Service\Worker\WorkerConfiguration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WorkerActivationServiceTest extends TestCase {
	public function testUnavailableQueueDoesNotStartWorkers(): void {
		$config = $this->createMock(WorkerConfiguration::class);
		$config->method('isAsyncLocalEnabled')->willReturn(true);
		$coordinator = $this->createMock(LocalWorkerCoordinator::class);
		$coordinator->method('ensureCapacity')->willThrowException(new \RuntimeException('Query failed'));
		$service = new WorkerActivationService($config, $coordinator, $this->createMock(LoggerInterface::class));
		$this->assertFalse($service->ensureWorkerRunning());
	}
	public function testProgrammingErrorsAreNotHiddenByTheHealthCheck(): void {
		$config = $this->createMock(WorkerConfiguration::class);
		$config->method('isAsyncLocalEnabled')->willReturn(true);
		$coordinator = $this->createMock(LocalWorkerCoordinator::class);
		$coordinator->method('ensureCapacity')->willThrowException(new \TypeError('Unexpected worker contract'));
		$service = new WorkerActivationService($config, $coordinator, $this->createMock(LoggerInterface::class));
		$this->expectException(\TypeError::class);
		$service->ensureWorkerRunning();
	}

	public function testStartsOnlyWhenThereAreJobs(): void {
		$config = $this->createMock(WorkerConfiguration::class);
		$config->method('isAsyncLocalEnabled')->willReturn(true);
		$config->method('getDesiredWorkerCount')->willReturn(4);
		$coordinator = $this->createMock(LocalWorkerCoordinator::class);
		$coordinator->expects($this->once())->method('ensureCapacity')->with(4)->willReturn(true);
		$service = new WorkerActivationService($config, $coordinator, $this->createMock(LoggerInterface::class));
		$this->assertTrue($service->ensureWorkerRunning());
	}
	public function testExternalModeNeverStartsLocalWorkers(): void {
		$config = $this->createMock(WorkerConfiguration::class);
		$config->method('isAsyncLocalEnabled')->willReturn(false);
		$coordinator = $this->createMock(LocalWorkerCoordinator::class);
		$coordinator->expects($this->never())->method('ensureCapacity');
		$service = new WorkerActivationService($config, $coordinator, $this->createMock(LoggerInterface::class));
		$this->assertFalse($service->ensureWorkerRunning());
	}
}
