<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use Psr\Log\LoggerInterface;

class WorkerHealthService {
	public function __construct(
		private WorkerConfiguration $workerConfiguration,
		private WorkerCounter $workerCounter,
		private WorkerJobCounter $workerJobCounter,
		private StartThrottlePolicy $startThrottlePolicy,
		private WorkerStarter $workerStarter,
		private LoggerInterface $logger,
	) {
	}

	public function isAsyncLocalEnabled(): bool {
		return $this->workerConfiguration->isAsyncLocalEnabled();
	}

	public function ensureWorkerRunning(): bool {
		try {
			if (!$this->workerConfiguration->isAsyncLocalEnabled()) {
				return false;
			}

			$workersNeeded = $this->calculateWorkersNeeded();
			if ($workersNeeded === 0) {
				return true;
			}

			if ($this->startThrottlePolicy->isThrottled()) {
				return true;
			}

			$this->startThrottlePolicy->recordAttempt();
			$this->workerStarter->startWorkers($workersNeeded);
			return true;
		} catch (\Throwable $e) {
			$this->logger->error('Failed to ensure worker is running: {error}', [
				'error' => $e->getMessage(),
				'exception' => $e,
			]);
			return false;
		}
	}

	private function calculateWorkersNeeded(): int {
		$desired = $this->workerConfiguration->getDesiredWorkerCount();
		$running = $this->workerCounter->countRunning();
		$pendingJobs = $this->workerJobCounter->countPendingJobs();

		if ($this->hasNoPendingWork($pendingJobs)) {
			return 0;
		}

		return $this->limitWorkersByAvailableWork($pendingJobs, $desired, $running);
	}

	private function hasNoPendingWork(int $pendingJobs): bool {
		return $pendingJobs === 0;
	}

	private function limitWorkersByAvailableWork(int $pendingJobs, int $desired, int $running): int {
		$workersNeeded = $desired - $running;
		$workersLimitedByJobs = min($pendingJobs, $workersNeeded);
		return max(0, $workersLimitedByJobs);
	}
}
