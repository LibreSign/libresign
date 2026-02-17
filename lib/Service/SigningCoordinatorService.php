<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCP\IAppConfig;

class SigningCoordinatorService {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function shouldUseParallelProcessing(int $signRequestCount): bool {
		if ($signRequestCount <= 1) {
			return false;
		}

		$signingMode = $this->appConfig->getValueString(Application::APP_ID, 'signing_mode', 'sync');
		return $signingMode === 'async';
	}
}
