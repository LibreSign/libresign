<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCP\IBinaryFinder;

class WorkerStarter {
	private const MAX_WORKERS = 32;

	public function __construct(
		private IBinaryFinder $binaryFinder,
	) {
	}

	public function startWorkers(int $count): void {
		$phpPath = $this->findPhpBinary();
		$occPath = \OC::$SERVERROOT . '/occ';
		$numWorkers = $this->clampWorkerCount($count);
		$jobClasses = [SignFileJob::class, SignSingleFileJob::class];

		for ($i = 0; $i < $numWorkers; $i++) {
			$this->executeCommand($phpPath, $occPath, $jobClasses);
		}
	}

	private function findPhpBinary(): string {
		$phpPath = $this->binaryFinder->findBinaryPath('php');
		return $phpPath !== false ? $phpPath : 'php';
	}

	private function clampWorkerCount(int $count): int {
		return max(1, min($count, self::MAX_WORKERS));
	}

	private function executeCommand(string $phpPath, string $occPath, array $jobClasses): void {
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
