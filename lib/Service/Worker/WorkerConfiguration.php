<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use OCA\Libresign\AppInfo\Application;
use OCP\IAppConfig;

class WorkerConfiguration {
	private const MAX_WORKERS = 32;
	private const DEFAULT_WORKERS = 4;

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function isAsyncLocalEnabled(): bool {
		$signingMode = $this->appConfig->getValueString(Application::APP_ID, 'signing_mode', 'sync');
		if ($signingMode !== 'async') {
			return false;
		}

		$workerType = $this->appConfig->getValueString(Application::APP_ID, 'worker_type', 'local');
		return $workerType === 'local';
	}

	public function getDesiredWorkerCount(): int {
		$numWorkers = $this->appConfig->getValueInt(Application::APP_ID, 'parallel_workers', self::DEFAULT_WORKERS);
		return max(1, min($numWorkers, self::MAX_WORKERS));
	}
}
