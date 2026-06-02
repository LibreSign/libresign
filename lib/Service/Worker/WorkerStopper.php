<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use Psr\Log\LoggerInterface;

class WorkerStopper {
	private const JOB_CLASSES = [
		SignFileJob::class,
		SignSingleFileJob::class,
	];

	public function __construct(
		private LoggerInterface $logger,
	) {
	}

	public function stopAll(): int {
		$processList = $this->getProcessList();
		if ($processList === '') {
			return 0;
		}

		$stopped = 0;
		foreach ($this->parseProcessList($processList) as [$pid, $args]) {
			if ($pid === null || $args === null) {
				continue;
			}

			if (!$this->matchesWorkerCommand($args)) {
				continue;
			}

			if ($this->terminateProcess($pid)) {
				$stopped++;
			}
		}

		return $stopped;
	}

	private function getProcessList(): string {
		try {
			$output = shell_exec('ps -eo pid=,args=');
			return $output === null ? '' : trim($output);
		} catch (\Throwable $e) {
			$this->logger->debug('Failed to read process list', [
				'error' => $e->getMessage(),
			]);
			return '';
		}
	}

	private function parseProcessList(string $processList): array {
		$lines = preg_split('/\R/', $processList) ?: [];
		$entries = [];

		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}

			$parts = preg_split('/\s+/', $line, 2);
			if (!$parts || count($parts) < 2) {
				continue;
			}

			$pid = (int)$parts[0];
			if ($pid <= 0) {
				continue;
			}

			$entries[] = [$pid, $parts[1]];
		}

		return $entries;
	}

	private function matchesWorkerCommand(string $args): bool {
		$occPath = \OC::$SERVERROOT . '/occ';
		if (strpos($args, $occPath) === false) {
			return false;
		}
		if (strpos($args, 'background-job:worker') === false) {
			return false;
		}

		foreach (self::JOB_CLASSES as $jobClass) {
			if (strpos($args, $jobClass) !== false) {
				return true;
			}
		}

		return false;
	}

	private function terminateProcess(int $pid): bool {
		if (function_exists('posix_kill')) {
			return @posix_kill($pid, SIGTERM);
		}

		if (!function_exists('shell_exec')) {
			$this->logger->debug('Cannot stop worker without posix_kill or shell_exec', [
				'pid' => $pid,
			]);
			return false;
		}

		shell_exec(sprintf('kill -TERM %d 2>/dev/null', $pid));
		return true;
	}
}
