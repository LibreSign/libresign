<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
