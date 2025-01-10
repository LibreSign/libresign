<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use OCA\Libresign\AppInfo\Application;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version7000Date20221026003343 extends SimpleMigrationStep {
	public function __construct(
		protected IAppConfig $appConfig,
	) {
	}

	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$rootCert = [];
		if ($commonName = $this->appConfig->getValueString(Application::APP_ID, 'commonName')) {
			$rootCert['commonName'] = $commonName;
			$this->appConfig->deleteKey(Application::APP_ID, 'commonName');
		}
		if ($country = $this->appConfig->getValueString(Application::APP_ID, 'country')) {
			$rootCert['names']['C'] = $country;
			$this->appConfig->deleteKey(Application::APP_ID, 'country');
		}
		if ($organization = $this->appConfig->getValueString(Application::APP_ID, 'organization')) {
			$rootCert['names']['O'] = $organization;
			$this->appConfig->deleteKey(Application::APP_ID, 'organization');
		}
		if ($organizationalUnit = $this->appConfig->getValueString(Application::APP_ID, 'organizationalUnit')) {
			$rootCert['names']['OU'] = $organizationalUnit;
			$this->appConfig->deleteKey(Application::APP_ID, 'organizationalUnit');
		}
		if ($rootCert) {
			$this->appConfig->setValueArray(Application::APP_ID, 'rootCert', $rootCert);
		}
	}
}
