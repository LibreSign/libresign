<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\BackgroundJob\SignFileJob;
use OCA\Libresign\BackgroundJob\SignSingleFileJob;
use OCA\Libresign\Service\Policy\Provider\Worker\WorkerConfigPolicy;
use OCP\IAppConfig;

class WorkerConfiguration {
	private const int MAX_WORKERS = 32;
	private const int DEFAULT_WORKERS = 4;

	public function __construct(
		private IAppConfig $appConfig,
		private WorkerConfigPolicy $workerConfigPolicy,
	) {
	}

	/**
	 * Queue signing jobs regardless of whether cron or a dedicated process consumes them.
	 */
	public function isAsyncLocalEnabled(): bool {
		if (!$this->isAsyncSigningEnabled()) {
			return false;
		}
		return $this->getWorkerConfig()['worker_type'] === 'local';
	}

	public function isAsyncSigningEnabled(): bool {
		return $this->appConfig->getValueString(Application::APP_ID, 'signing_mode', 'sync') === 'async';
	}

	/** @return list<class-string> */
	public function getSigningJobClasses(): array {
		return [SignFileJob::class, SignSingleFileJob::class];
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
