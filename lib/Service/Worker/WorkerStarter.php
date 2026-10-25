<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
use OCP\IBinaryFinder;

/** Starts a detached worker. The caller MUST hold the local registry lock. */
class WorkerStarter {
	public function __construct(
		private IBinaryFinder $binaryFinder,
		private WorkerConfiguration $workerConfiguration,
	) {
	}

	public function startWorker(): int {
		$php = $this->binaryFinder->findBinaryPath('php');
		if ($php === false) {
			throw new \RuntimeException('PHP CLI is unavailable');
		}
		$command = [
			$php,
			\OC::$SERVERROOT . '/occ',
			'background-job:worker',
			...$this->workerConfiguration->getSigningJobClasses(),
			'--stop_after=30m',
			'--interval=0',
		];
		$process = $this->createProcess($command);
		// Request that Symfony detach the child from the HTTP request lifetime.
		$process->setOptions(['create_new_console' => true]);
		// The HTTP request cannot keep reading child stdout/stderr. Detached
		// pipes would close when Symfony destroys the Process object, and a
		// worker writing progress to stdout could then terminate on SIGPIPE.
		$process->disableOutput();
		$process->setTimeout(null);
		$process->start();
		$pid = (int)$process->getPid();
		if ($pid <= 0) {
			throw new \RuntimeException('Worker was started without a valid process identifier');
		}
		return $pid;
	}

	protected function createProcess(array $command): Process {
		return new Process($command);
	}
}
