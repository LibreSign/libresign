<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCA\Libresign\Db\FileMapper;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\IJobList;
use Psr\Log\LoggerInterface;

class WorkerJobCounter {
	public function __construct(
		private IJobList $jobList,
		private FileMapper $fileMapper,
		private LoggerInterface $logger,
	) {
	}

	public function countPendingJobs(): int {
		try {
			$total = 0;

			$total += $this->countPendingSignFileJobs();
			$total += $this->countPendingSignSingleFileJobs();

			return $total;
		} catch (\Throwable $e) {
			$this->logger->debug('Failed to count pending jobs', [
				'error' => $e->getMessage(),
			]);
			return 0;
		}
	}

	private function countPendingSignFileJobs(): int {
		$total = 0;
		foreach ($this->jobList->getJobsIterator(SignFileJob::class, null, 0) as $job) {
			$total += $this->resolveWorkUnitsFromSignFileJob($job);
		}
		return $total;
	}

	private function countPendingSignSingleFileJobs(): int {
		$total = 0;
		foreach ($this->jobList->getJobsIterator(SignSingleFileJob::class, null, 0) as $job) {
			$total++;
		}
		return $total;
	}

	private function resolveWorkUnitsFromSignFileJob(IJob $job): int {
		$argument = $job->getArgument();
		if (!is_array($argument)) {
			return 1;
		}

		$fileId = $argument['fileId'] ?? null;
		if ($fileId === null) {
			return 1;
		}

		try {
			$file = $this->fileMapper->getById((int)$fileId);
		} catch (\Throwable) {
			return 1;
		}

		if (!$file->isEnvelope()) {
			return 1;
		}

		$count = $this->fileMapper->countChildrenFiles($file->getId());
		return max(1, $count);
	}
}
