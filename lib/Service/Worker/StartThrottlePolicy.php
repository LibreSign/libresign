<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Worker;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;

class StartThrottlePolicy {
	private const CONFIG_KEY = 'worker_last_start_attempt';
	private const MIN_INTERVAL_BETWEEN_STARTS = 10;

	public function __construct(
		private IAppConfig $appConfig,
		private ITimeFactory $timeFactory,
	) {
	}

	public function isThrottled(): bool {
		$lastAttempt = (int)$this->appConfig->getValueInt('libresign', self::CONFIG_KEY, 0);
		$now = $this->timeFactory->getTime();
		return ($now - $lastAttempt) < self::MIN_INTERVAL_BETWEEN_STARTS;
	}

	public function recordAttempt(): void {
		$this->appConfig->setValueInt('libresign', self::CONFIG_KEY, $this->timeFactory->getTime());
	}
}
