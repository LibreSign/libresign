<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Provider\Worker\WorkerConfigPolicy;
use OCP\IAppConfig;

class WorkerConfiguration {
	private const MAX_WORKERS = 32;
	private const DEFAULT_WORKERS = 4;

	public function __construct(
		private IAppConfig $appConfig,
		private WorkerConfigPolicy $workerConfigPolicy,
	) {
	}

	public function isAsyncLocalEnabled(): bool {
		$signingMode = $this->appConfig->getValueString(Application::APP_ID, 'signing_mode', 'sync');
		if ($signingMode !== 'async') {
			return false;
		}

		$workerConfig = $this->getWorkerConfig();
		return $workerConfig['worker_type'] === 'local';
	}

	public function getDesiredWorkerCount(): int {
		$workerConfig = $this->getWorkerConfig();
		$numWorkers = (int)($workerConfig['parallel_workers'] ?? self::DEFAULT_WORKERS);
		return max(1, min($numWorkers, self::MAX_WORKERS));
	}

	/**
	 * @return array{worker_type: string, parallel_workers: int}
	 */
	private function getWorkerConfig(): array {
		$rawValue = $this->appConfig->getValueString(
			Application::APP_ID,
			WorkerConfigPolicy::SYSTEM_APP_CONFIG_KEY,
			'',
		);

		if ($rawValue === '') {
			return $this->workerConfigPolicy->defaultValue();
		}

		return $this->workerConfigPolicy->normalizeValue($rawValue);
	}
}
