<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use OCP\AppFramework\Services\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version7000Date20221026003343 extends SimpleMigrationStep {
	public function __construct(
		protected IAppConfig $appConfig,
	) {
	}

	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$rootCert = [];
		if ($commonName = $this->appConfig->getAppValue('commonName')) {
			$rootCert['commonName'] = $commonName;
			$this->appConfig->deleteAppValue('commonName');
		}
		if ($country = $this->appConfig->getAppValue('country')) {
			$rootCert['names']['C'] = $country;
			$this->appConfig->deleteAppValue('country');
		}
		if ($organization = $this->appConfig->getAppValue('organization')) {
			$rootCert['names']['O'] = $organization;
			$this->appConfig->deleteAppValue('organization');
		}
		if ($organizationalUnit = $this->appConfig->getAppValue('organizationalUnit')) {
			$rootCert['names']['OU'] = $organizationalUnit;
			$this->appConfig->deleteAppValue('organizationalUnit');
		}
		if ($rootCert) {
			$this->appConfig->setAppValue('rootCert', json_encode($rootCert));
		}
	}
}
