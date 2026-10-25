<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use Psr\Log\LoggerInterface;

/** Best-effort local launch coordination; not process supervision. */
class LocalWorkerCoordinator {
	private const int COOLDOWN_SECONDS = 2;

	public function __construct(
		private WorkerStarter $starter,
		private WorkerJobCounter $jobCounter,
		private LocalWorkerProcess $processes,
		private LocalWorkerStateStore $store,
		private LoggerInterface $logger,
	) {
	}

	public function ensureCapacity(int $desired): bool {
		if ($desired <= 0) {
			return true;
		}
		if (!$this->processes->isSupported()) {
			$this->logger->warning('Local workers require Linux; use Nextcloud cron or dedicated workers');
			return false;
		}

		return $this->store->withExclusiveLock(fn (): bool => $this->launchUnderLock(min($desired, 32)));
	}

	private function launchUnderLock(int $desired): bool {
		$state = $this->store->readState();
		$running = [];
		foreach ($state['workers'] ?? [] as $entry) {
			if (is_array($entry) && $this->processes->isRunning((int)($entry['pid'] ?? 0), (string)($entry['start'] ?? ''))) {
				$running[] = $entry;
			}
		}

		$capacity = max(0, $desired - count($running));
		$wanted = 0;
		if ($capacity > 0 && microtime(true) - (float)($state['lastAttempt'] ?? 0) >= self::COOLDOWN_SECONDS) {
			$wanted = min($capacity, $this->jobCounter->countEligibleJobs($capacity));
			if ($wanted > 0) {
				$state['lastAttempt'] = microtime(true);
			}
			for ($i = 0; $i < $wanted; $i++) {
				try {
					$pid = $this->starter->startWorker();
					$startTime = $this->processes->startTime($pid);
					if ($startTime !== null) {
						$running[] = ['pid' => $pid, 'start' => $startTime];
					} else {
						$this->logger->warning('Worker process identity is unavailable', ['pid' => $pid]);
					}
				} catch (\RuntimeException $e) {
					$this->logger->warning('Cannot start local signing worker', ['exception' => $e]);
					break;
				}
			}
		}
		$state['workers'] = $running;
		$this->store->writeState($state);
		return $running !== [] || $wanted === 0;
	}

}
