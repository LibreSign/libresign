<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version8000Date20230608004729 extends SimpleMigrationStep {
	public function __construct(
		protected IAppConfig $appConfig,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$cfsslBin = $this->appConfig->getAppValue('cfssl_bin');
		$cfsslUrl = $this->appConfig->getAppValue('cfssl_url');
		if ($cfsslBin || $cfsslUrl) {
			$this->appConfig->setAppValue('certificate_engine', 'cfssl');
		}
	}
}
