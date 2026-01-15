<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Service\Worker\StartThrottlePolicy;
use OCA\Libresign\Service\Worker\WorkerConfiguration;
use OCA\Libresign\Service\Worker\WorkerCounter;
use OCA\Libresign\Service\Worker\WorkerStarter;
use Psr\Log\LoggerInterface;

class WorkerHealthService {
	public function __construct(
		private WorkerConfiguration $config,
		private WorkerCounter $counter,
		private StartThrottlePolicy $throttle,
		private WorkerStarter $starter,
		private LoggerInterface $logger,
	) {
	}

	public function ensureWorkerRunning(): bool {
		try {
			if (!$this->config->isAsyncLocalEnabled()) {
				return false;
			}

			$workersNeeded = $this->calculateWorkersNeeded();
			if ($workersNeeded === 0) {
				return true;
			}

			if ($this->throttle->isThrottled()) {
				return true;
			}

			$this->throttle->recordAttempt();
			$this->starter->startWorkers($workersNeeded);
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
		$desired = $this->config->getDesiredWorkerCount();
		$running = $this->counter->countRunning();
		return max(0, $desired - $running);
	}
}
