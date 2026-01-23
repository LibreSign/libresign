<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCP\BackgroundJob\IJobList;
use Psr\Log\LoggerInterface;

class WorkerJobCounter {
	public function __construct(
		private IJobList $jobList,
		private LoggerInterface $logger,
	) {
	}

	public function countPendingJobs(): int {
		try {
			$counts = $this->jobList->countByClass();
			$total = 0;

			foreach ($counts as $row) {
				if ($row['class'] === SignFileJob::class || $row['class'] === SignSingleFileJob::class) {
					$total += $row['count'];
				}
			}

			return $total;
		} catch (\Throwable $e) {
			$this->logger->debug('Failed to count pending jobs', [
				'error' => $e->getMessage(),
			]);
			return 0;
		}
	}
}
