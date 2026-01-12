<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IAppConfig;
use OCP\IBinaryFinder;
use Psr\Log\LoggerInterface;

/**
 * Service to ensure the signing worker is running and healthy.
 *
 * Checks if a worker process is needed and starts it on demand.
 */
class WorkerHealthService {
	private const CONFIG_KEY = 'worker_last_start_attempt';
	private const MIN_INTERVAL_BETWEEN_STARTS = 10; // seconds, avoid hammering with start attempts

	public function __construct(
		private IJobList $jobList,
		private IAppConfig $appConfig,
		private ITimeFactory $timeFactory,
		private IBinaryFinder $binaryFinder,
		private LoggerInterface $logger,
	) {
	}

	public function isAsyncLocalEnabled(): bool {
		$signingMode = $this->appConfig->getValueString(Application::APP_ID, 'signing_mode', 'async');
		if ($signingMode !== 'async') {
			return false;
		}

		$workerType = $this->appConfig->getValueString(Application::APP_ID, 'worker_type', 'local');
		if ($workerType !== 'local') {
			return false;
		}

		return true;
	}

	/**
	 * Ensure a worker is running to process signing jobs.
	 * Start a local worker only when there are LibreSign jobs queued.
	 */
	public function ensureWorkerRunning(): bool {
		try {
			if (!$this->isAsyncLocalEnabled()) {
				return false;
			}

			$desiredWorkers = $this->getDesiredWorkerCount();
			$runningWorkers = $this->countRunningWorkers();
			$needed = max(0, $desiredWorkers - $runningWorkers);

			if ($needed === 0) {
				return true; // already have enough workers
			}

			$this->attemptStartWorker($needed);
			return true;
		} catch (\Throwable $e) {
			// Log but don't fail the request if worker check/start fails
			$this->logger->error('Failed to ensure worker is running: {error}', [
				'error' => $e->getMessage(),
				'exception' => $e,
			]);
			return false;
		}
	}

	// NOTE: no health probing here; we rely on throttled start attempts and Nextcloud's worker lifecycle

	/**
	 * Attempt to start a worker process.
	 * Checks throttling to avoid starting too many processes.
	 */
	private function attemptStartWorker(int $needed): void {
		$lastAttempt = (int)$this->appConfig->getValueInt('libresign', self::CONFIG_KEY, 0);
		$now = $this->timeFactory->getTime();
		$timeSinceLastAttempt = $now - $lastAttempt;

		if ($needed <= 0) {
			return;
		}

		if ($timeSinceLastAttempt < self::MIN_INTERVAL_BETWEEN_STARTS) {
			return;
		}

		$this->appConfig->setValueInt('libresign', self::CONFIG_KEY, $now);
		$this->startWorkerProcess($needed);
	}

	/**
	 * Start multiple signing workers in background.
	 * Uses Nextcloud's native background job worker to handle both SignFileJob (envelope coordinator)
	 * and SignSingleFileJob (individual file signing).
	 * Number of workers configurable via app config 'parallel_workers' (default: 4).
	 */
	private function startWorkerProcess(int $count): void {
		$occPath = \OC::$SERVERROOT . '/occ';
		// Resolve the PHP CLI binary via IBinaryFinder (avoid PHP_BINARY under FPM)
		$phpPath = $this->binaryFinder->findBinaryPath('php');
		if ($phpPath === false) {
			$phpPath = 'php';
		}

		// Clamp count between 1 and 32
		$numWorkers = max(1, min($count, 32));

		// Start multiple workers in parallel
		for ($i = 0; $i < $numWorkers; $i++) {
			// SECURITY: Specify LibreSign job classes explicitly to prevent processing other apps' jobs
			// This ensures our workers only handle LibreSign signing tasks
			$jobClasses = [
				SignFileJob::class,
				SignSingleFileJob::class,
			];
			$jobClassesArg = implode(' ', array_map('escapeshellarg', $jobClasses));

			$cmd = sprintf(
				'%s %s background-job:worker %s --stop_after=1h >> /dev/null 2>&1 &',
				escapeshellarg($phpPath),
				escapeshellarg($occPath),
				$jobClassesArg
			);
			shell_exec($cmd);
		}
	}

	private function getDesiredWorkerCount(): int {
		$numWorkers = $this->appConfig->getValueInt(Application::APP_ID, 'parallel_workers', 4);
		return max(1, min($numWorkers, 32));
	}

	protected function countRunningWorkers(): int {
		try {
			$occPath = \OC::$SERVERROOT . '/occ';
			$cmd = sprintf(
				"ps -eo args | grep -F %s | grep -F 'background-job:worker' | grep -E 'SignFileJob|SignSingleFileJob' | grep -v grep | wc -l",
				escapeshellarg($occPath)
			);
			$output = shell_exec($cmd);
			return max(0, (int)trim((string)$output));
		} catch (\Throwable $e) {
			$this->logger->debug('Failed to count running workers', [
				'error' => $e->getMessage(),
			]);
			return 0;
		}
	}
}
