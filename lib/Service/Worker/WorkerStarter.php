<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCA\Libresign\Service\Process\ProcessManager;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
use OCP\IBinaryFinder;

class WorkerStarter {
	private const MAX_WORKERS = 32;
	private const PROCESS_SOURCE = 'worker';

	public function __construct(
		private IBinaryFinder $binaryFinder,
		private ProcessManager $processManager,
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
		$command = [
			$phpPath,
			$occPath,
			'background-job:worker',
			...$jobClasses,
			'--stop_after=30m',
		];
		$process = $this->createProcess($command);
		$process->setOptions(['create_new_console' => true]);
		$process->setTimeout(null);
		$process->start();

		$pid = $process->getPid() ?? 0;
		if ($pid > 0) {
			$this->processManager->register(self::PROCESS_SOURCE, $pid);
		}
	}

	protected function createProcess(array $command): Process {
		return new Process($command);
	}
}
