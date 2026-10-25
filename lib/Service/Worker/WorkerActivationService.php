<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use OCP\DB\Exception as DatabaseException;
use Psr\Log\LoggerInterface;

class WorkerActivationService {
	public function __construct(
		private WorkerConfiguration $workerConfiguration,
		private LocalWorkerCoordinator $coordinator,
		private LoggerInterface $logger,
	) {
	}

	public function isAsyncLocalEnabled(): bool {
		return $this->workerConfiguration->isAsyncLocalEnabled();
	}

	/** Best effort. A failed local launch does not invalidate a persisted job. */
	public function ensureWorkerRunning(): bool {
		try {
			if (!$this->isAsyncLocalEnabled()) {
				return false;
			}
			return $this->coordinator->ensureCapacity($this->workerConfiguration->getDesiredWorkerCount());
		} catch (\RuntimeException|DatabaseException $e) {
			// A count failure is not an empty queue and must not fail signing.
			$this->logger->warning('Unable to check or start signing workers; Nextcloud cron can process pending jobs', ['exception' => $e]);
			return false;
		}
	}
}
