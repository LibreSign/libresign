<?php

declare(strict_types=1);

namespace OCA\Libresign\Service\Worker;

use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCP\IBinaryFinder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class WorkerStarter {
	private const MAX_WORKERS = 32;

	public function __construct(
		private IBinaryFinder $binaryFinder,
		private LoggerInterface $logger,
	) {
	}

	public function startWorkers(int $count): void {
		$phpPath = $this->findPhpBinary();
		$occPath = \OC::$SERVERROOT . '/occ';
		$numWorkers = $this->clampWorkerCount($count);
		$jobClasses = [SignFileJob::class, SignSingleFileJob::class];

		for ($i = 0; $i < $numWorkers; $i++) {
			$this->startWorker($phpPath, $occPath, $jobClasses);
		}
	}

	private function findPhpBinary(): string {
		$phpPath = $this->binaryFinder->findBinaryPath('php');
		return $phpPath !== false ? $phpPath : 'php';
	}

	private function clampWorkerCount(int $count): int {
		return max(1, min($count, self::MAX_WORKERS));
	}

	private function startWorker(string $phpPath, string $occPath, array $jobClasses): void {
		$jobClassesArg = implode(' ', array_map('escapeshellarg', $jobClasses));
		$cmdLine = sprintf(
			'%s %s background-job:worker %s --stop_after=30s',
			escapeshellarg($phpPath),
			escapeshellarg($occPath),
			$jobClassesArg
		);

		try {
			$process = new Process(explode(' ', $cmdLine));
			$process->start();

			if ($process->getPid() !== null) {
				$this->logger->info('Worker started');
			} else {
				$this->logger->warning('Failed to start worker');
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to start worker', ['error' => $e->getMessage()]);
		}
	}
}
