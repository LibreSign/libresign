<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Worker;

use OCA\Libresign\BackgroundJob\DeliverNotification;
use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\Service\Worker\LocalWorkerCoordinator;
use OCA\Libresign\Service\Worker\LocalWorkerProcess;
use OCA\Libresign\Service\Worker\LocalWorkerStateStore;
use OCA\Libresign\Service\Worker\WorkerConfiguration;
use OCA\Libresign\Service\Worker\WorkerJobCounter;
use OCA\Libresign\Service\Worker\WorkerStarter;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/** End-to-end contract: the local notification pool drains only its own jobs. */
class LocalNotificationWorkerTest extends TestCase {
	public function testNotificationWorkerDrainsItsQueueAndExits(): void {
		$process = new LocalWorkerProcess();
		if (!$process->isSupported()) {
			$this->markTestSkipped('Local workers require readable Linux procfs');
		}
		$this->assertFalse(Server::get(IDBConnection::class)->inTransaction());

		$queue = Server::get(IJobList::class);
		$notification = ['id' => random_int(1000000000, 1900000000)];
		$signing = ['fileId' => -random_int(1000000000, 1900000000), 'signRequestId' => -1, 'test' => 'notification-isolation'];
		$directory = sys_get_temp_dir() . '/libresign-worker-contract-' . bin2hex(random_bytes(8));
		$state = new LocalWorkerStateStore($directory);
		$coordinator = new LocalWorkerCoordinator(
			Server::get(WorkerStarter::class), Server::get(WorkerJobCounter::class),
			$process, $state, Server::get(LoggerInterface::class),
		);

		try {
			$queue->add(SignFileJob::class, $signing);
			$queue->add(DeliverNotification::class, $notification);
			$this->assertTrue($coordinator->ensureCapacity(1, WorkerConfiguration::NOTIFICATION));
			$workers = $state->readState(WorkerConfiguration::NOTIFICATION)['workers'] ?? [];
			$this->assertCount(1, $workers);
			['pid' => $pid, 'start' => $start] = $workers[0];
			$deadline = microtime(true) + 20;
			while (microtime(true) < $deadline
				&& ($queue->has(DeliverNotification::class, $notification)
					|| $process->isRunning($pid, $start, [DeliverNotification::class]))) {
				usleep(50000);
			}
			$this->assertFalse($queue->has(DeliverNotification::class, $notification));
			$this->assertTrue($queue->has(SignFileJob::class, $signing));
			$this->assertFalse($process->isRunning($pid, $start, [DeliverNotification::class]));
		} finally {
			$queue->remove(DeliverNotification::class, $notification);
			$queue->remove(SignFileJob::class, $signing);
			foreach (glob($directory . '/*') ?: [] as $file) {
				@unlink($file);
			}
			@rmdir($directory);
		}
	}
}
