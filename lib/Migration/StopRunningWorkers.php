<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use OCA\Libresign\Service\Worker\WorkerStopper;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

class StopRunningWorkers implements IRepairStep {
	public function __construct(
		private WorkerStopper $stopper,
		private LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Stop running LibreSign workers';
	}

	#[\Override]
	public function run(IOutput $output): void {
		try {
			$stopped = $this->stopper->stopAll();
			if ($stopped > 0) {
				$output->info('Stopped ' . $stopped . ' LibreSign worker(s).');
			}
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to stop LibreSign workers during upgrade', [
				'error' => $e->getMessage(),
				'exception' => $e,
			]);
		}
	}
}
