<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Worker;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCA\Libresign\Service\Worker\WorkerJobCounter;
use OCP\App\IAppManager;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\TestCase;

class WorkerJobCounterTest extends TestCase {
	/**
	 * This is deliberately a contract tripwire: Nextcloud does not expose an
	 * OCP API to count due, unreserved jobs without claiming them. If core
	 * changes its job storage, CI must fail with a useful migration action.
	 */
	public function testNextcloudJobTableContractUsedByReadinessHint(): void {
		$db = Server::get(IDBConnection::class);
		try {
			$query = $db->getQueryBuilder();
			$result = $query->select('id', 'class', 'reserved_at', 'last_checked')
				->from('jobs')->setMaxResults(1)->executeQuery();
			$result->closeCursor();
		} catch (\Throwable $e) {
			self::fail('Nextcloud job storage contract changed: expected jobs(id, class, reserved_at, last_checked). '
				. 'Review upstream JobList::getNext() and update WorkerJobCounter::countEligibleJobs() '
				. 'to match its due/reservation rules before supporting this Nextcloud version. Cause: ' . $e->getMessage());
		}
		$this->addToAssertionCount(1);
	}

	public function testFutureOnlyQueueDoesNotStartEmptyWorker(): void {
		Server::get(IAppManager::class)->loadApp('libresign');
		$queue = Server::get(IJobList::class);
		$counter = Server::get(WorkerJobCounter::class);
		$args = ['fileId' => -random_int(900000, 999999), 'signRequestId' => -1, 'test' => 'contract-future'];
		try {
			$queue->scheduleAfter(SignSingleFileJob::class, time() + 3600, $args);
			$this->assertSame(0, $counter->countEligibleJobs(4),
				'Nextcloud changed scheduled-job eligibility. Compare with JobList::getNext() and update WorkerJobCounter before upgrade.');
		} finally {
			$queue->remove(SignSingleFileJob::class, $args);
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
				'Nextcloud queue eligibility drifted: verify scheduled jobs and reserved_at in JobList::getNext()');
			$this->assertSame(1, $counter->countEligibleJobs(1),
				'The capacity-limited count must never exceed available worker slots');
			$reserved = $queue->getNext(false, [SignFileJob::class]);
			$this->assertNotNull($reserved);
			$this->assertSame(1, $counter->countEligibleJobs(32),
				'Nextcloud reservation contract changed: compare JobList::getNext() with WorkerJobCounter');
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
