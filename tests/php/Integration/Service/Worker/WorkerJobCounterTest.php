<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Worker;

use OCA\Libresign\BackgroundJob\DeliverNotification;
use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCA\Libresign\Service\Worker\WorkerConfiguration;
use OCA\Libresign\Service\Worker\WorkerJobCounter;
use OCP\App\IAppManager;
use OCP\BackgroundJob\IJobList;
use OCP\Server;
use PHPUnit\Framework\TestCase;

class WorkerJobCounterTest extends TestCase {
	public function testFutureOnlyQueueDoesNotStartEmptyWorker(): void {
		Server::get(IAppManager::class)->loadApp('libresign');
		$queue = Server::get(IJobList::class);
		$counter = Server::get(WorkerJobCounter::class);
		$args = ['fileId' => -random_int(900000, 999999), 'signRequestId' => -1, 'test' => 'contract-future'];
		try {
			$queue->scheduleAfter(SignSingleFileJob::class, time() + 3600, $args);
			$this->assertSame(0, $counter->countEligibleJobs(4),
				'Future signing jobs must not consume local worker capacity.');
		} finally {
			$queue->remove(SignSingleFileJob::class, $args);
		}
	}

	public function testNotificationPoolExcludesSigningAndFutureJobs(): void {
		Server::get(IAppManager::class)->loadApp('libresign');
		$queue = Server::get(IJobList::class);
		$counter = Server::get(WorkerJobCounter::class);
		$base = random_int(1000000000, 1900000000);
		$due = ['id' => $base];
		$future = ['id' => $base + 1];
		$signing = ['fileId' => -$base, 'signRequestId' => -1, 'test' => 'notification-pool-isolation'];
		$before = $counter->countEligibleJobs(32, WorkerConfiguration::NOTIFICATION);
		try {
			$queue->add(DeliverNotification::class, $due);
			$queue->scheduleAfter(DeliverNotification::class, time() + 3600, $future);
			$queue->add(SignFileJob::class, $signing);
			$this->assertSame(min(32, $before + 1),
				$counter->countEligibleJobs(32, WorkerConfiguration::NOTIFICATION),
				'Notification readiness must ignore signing classes and future notifications');
			$this->assertSame(1, $counter->countEligibleJobs(1, WorkerConfiguration::NOTIFICATION));
		} finally {
			$queue->remove(DeliverNotification::class, $due);
			$queue->remove(DeliverNotification::class, $future);
			$queue->remove(SignFileJob::class, $signing);
		}
	}

	public function testOnlyDueUnreservedSigningJobsUseWorkerCapacity(): void {
		Server::get(IAppManager::class)->loadApp('libresign');
		$queue = Server::get(IJobList::class);
		$counter = Server::get(WorkerJobCounter::class);
		$base = random_int(900000, 999999);
		$due = ['fileId' => -$base, 'signRequestId' => -1, 'test' => 'due'];
		$future = ['fileId' => -$base - 1, 'signRequestId' => -1, 'test' => 'future'];
		$second = ['fileId' => -$base - 2, 'signRequestId' => -1, 'test' => 'second'];
		$reserved = null;
		try {
			$queue->add(SignFileJob::class, $due);
			$queue->add(SignFileJob::class, $second);
			$queue->scheduleAfter(SignSingleFileJob::class, time() + 3600, $future);
			$this->assertSame(2, $counter->countEligibleJobs(32),
				'Only ready, unclaimed signing jobs count toward worker capacity.');
			$this->assertSame(1, $counter->countEligibleJobs(1),
				'The capacity-limited count must never exceed available worker slots');
			$reserved = $queue->getNext(false, [SignFileJob::class]);
			$this->assertNotNull($reserved);
			$this->assertSame(1, $counter->countEligibleJobs(32),
				'A claimed signing job must no longer consume available capacity.');
		} finally {
			if ($reserved !== null) {
				$queue->unlockJob($reserved);
			}
			$queue->remove(SignFileJob::class, $due);
			$queue->remove(SignFileJob::class, $second);
			$queue->remove(SignSingleFileJob::class, $future);
		}
	}
}
