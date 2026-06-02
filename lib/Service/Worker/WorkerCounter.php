<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use Psr\Log\LoggerInterface;

class WorkerCounter {
	public function __construct(
		private LoggerInterface $logger,
	) {
	}

	public function countRunning(): int {
		try {
			$cmd = $this->buildCountCommand();
			$output = shell_exec($cmd);
			return max(0, (int)trim((string)$output));
		} catch (\Throwable $e) {
			$this->logger->debug('Failed to count running workers', [
				'error' => $e->getMessage(),
			]);
			return 0;
		}
	}

	private function buildCountCommand(): string {
		$occPath = \OC::$SERVERROOT . '/occ';
		return sprintf(
			"ps -eo args | grep -F %s | grep -F 'background-job:worker' | grep -E 'SignFileJob|SignSingleFileJob' | grep -v grep | wc -l",
			escapeshellarg($occPath)
		);
	}
}
