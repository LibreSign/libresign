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

	public function ensureCapacity(int $desired, string $pool = WorkerConfiguration::SIGNING): bool {
		if ($desired <= 0) {
			return true;
		}
		if (!$this->processes->isSupported()) {
			$this->logger->warning('Local workers require readable Linux procfs; use Nextcloud cron or dedicated workers');
			return false;
		}

		return $this->store->withExclusiveLock(fn (): bool => $this->launchUnderLock(min($desired, 32), $pool), $pool);
	}

	private function launchUnderLock(int $desired, string $pool): bool {
		$state = $this->store->readState($pool);
		$running = $this->runningWorkers($state['workers'] ?? [], $this->jobCounter->jobClasses($pool));
		$availableSlots = max(0, $desired - count($running));
		$toStart = $this->workersToStart($availableSlots, $state, $pool);

		if ($toStart > 0) {
			$state['lastAttempt'] = microtime(true);
			$running = $this->startWorkers($toStart, $running, $pool);
		}

		$state['workers'] = $running;
		$this->store->writeState($state, $pool);
		return $running !== [] || $toStart === 0;
	}

	/** @param array<mixed> $entries
	 * @param list<class-string> $jobClasses
	 * @return list<array{pid: int, start: string}>
	 */
	private function runningWorkers(array $entries, array $jobClasses): array {
		$running = [];
		foreach ($entries as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			$pid = (int)($entry['pid'] ?? 0);
			$startTime = (string)($entry['start'] ?? '');
			if ($this->processes->isRunning($pid, $startTime, $jobClasses)) {
				$running[] = ['pid' => $pid, 'start' => $startTime];
			}
		}
		return $running;
	}

	/** @param array<string, mixed> $state */
	private function workersToStart(int $availableSlots, array $state, string $pool): int {
		if ($availableSlots === 0) {
			return 0;
		}
		// A previously identified notification worker may exit after draining
		// its queue. Do not lose a new wake-up to the launch cooldown.
		// Keep the cooldown when no child identity was recorded.
		if (($pool === WorkerConfiguration::SIGNING || ($state['workers'] ?? []) === [])
			&& microtime(true) - (float)($state['lastAttempt'] ?? 0) < self::COOLDOWN_SECONDS) {
			return 0;
		}
		return min($availableSlots, $this->jobCounter->countEligibleJobs($availableSlots, $pool));
	}

	/** @param list<array{pid: int, start: string}> $running
	 * @return list<array{pid: int, start: string}>
	 */
	private function startWorkers(int $count, array $running, string $pool): array {
		for ($i = 0; $i < $count; $i++) {
			try {
				$pid = $this->starter->startWorker($pool);
				$startTime = $this->processes->startTime($pid);
				if ($startTime === null) {
					$this->logger->warning('Worker process identity is unavailable', ['pid' => $pid]);
					// The child may still be running. Do not spawn additional
					// workers without a verified identity.
					break;
				}
				$running[] = ['pid' => $pid, 'start' => $startTime];
			} catch (\RuntimeException $e) {
				$this->logger->warning('Cannot start local ' . $pool . ' worker', ['exception' => $e]);
				break;
			}
		}
		return $running;
	}
}
