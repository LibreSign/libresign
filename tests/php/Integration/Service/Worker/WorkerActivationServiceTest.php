<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Worker;

use OCA\Libresign\BackgroundJob\CleanupStaleSigningStatus;
use OCA\Libresign\BackgroundJob\DeliverNotification;
use OCA\Libresign\Service\Worker\LocalWorkerCoordinator;
use OCA\Libresign\Service\Worker\LocalWorkerProcess;
use OCA\Libresign\Service\Worker\LocalWorkerStateStore;
use OCA\Libresign\Service\Worker\WorkerConfiguration;
use OCA\Libresign\Service\Worker\WorkerActivationService;
use OCP\App\IAppManager;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\TestCase;

class WorkerActivationServiceTest extends TestCase {
	public function testRegisteredStaleSigningJobStillResolvesAfterUpgrade(): void {
		Server::get(IAppManager::class)->loadApp('libresign');
		$this->assertInstanceOf(CleanupStaleSigningStatus::class, Server::get(CleanupStaleSigningStatus::class));
	}

	public function testRealOccNotificationWorkerDrainsQueueAndExits(): void {
		$processes = new LocalWorkerProcess();
		if (!$processes->isSupported()) {
			$this->markTestSkipped('A readable Linux procfs is required for the local worker');
		}
		Server::get(IAppManager::class)->loadApp('libresign');
		$this->assertFalse(Server::get(IDBConnection::class)->inTransaction(),
			'A real OCC worker must not be started against an uncommitted integration fixture');
		$queue = Server::get(IJobList::class);
		$coordinator = Server::get(LocalWorkerCoordinator::class);
		$state = Server::get(LocalWorkerStateStore::class);
		$args = ['id' => random_int(1000000000, 1900000000)];
		$queue->add(DeliverNotification::class, $args);
		$queuedAt = microtime(true);
		try {
			$this->assertTrue($queue->has(DeliverNotification::class, $args));
			$this->assertTrue($coordinator->ensureCapacity(1, WorkerConfiguration::NOTIFICATION),
				'A local notification worker must start when a due job exists');
			$records = $state->readState(WorkerConfiguration::NOTIFICATION)['workers'] ?? [];
			$this->assertNotEmpty($records);
			$deadline = microtime(true) + 20;
			while ($queue->has(DeliverNotification::class, $args) && microtime(true) < $deadline) {
				usleep(50000);
			}
			$elapsedMs = (microtime(true) - $queuedAt) * 1000;
			$this->assertFalse($queue->has(DeliverNotification::class, $args),
				'The real OCC worker must drain the queued synthetic delivery');
			// Queue-to-drain includes OCC startup and processing, not just job-start latency.
			fwrite(STDERR, sprintf("Notification enqueue-to-drain: %.1f ms\n", $elapsedMs));
			foreach ($records as $record) {
				while ($processes->isRunning($record['pid'], $record['start'], [DeliverNotification::class])
					&& microtime(true) < $deadline) {
					usleep(50000);
				}
				$this->assertFalse($processes->isRunning($record['pid'], $record['start'], [DeliverNotification::class]),
					'An idle local notification worker must exit naturally');
			}
		} finally {
			$queue->remove(DeliverNotification::class, $args);
		}
	}

	public function testNextcloudResolvesRenamedWorkerServices(): void {
		Server::get(IAppManager::class)->loadApp('libresign');

		$activation = Server::get(WorkerActivationService::class);
		$coordinator = Server::get(LocalWorkerCoordinator::class);

		$this->assertInstanceOf(WorkerActivationService::class, $activation);
		$this->assertInstanceOf(LocalWorkerCoordinator::class, $coordinator);
		$this->assertTrue($coordinator->ensureCapacity(0));
	}
}
