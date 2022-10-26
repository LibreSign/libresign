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

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version7000Date20221026003343 extends SimpleMigrationStep {
	/** @var IConfig */
	protected $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$rootCert = [];
		if ($commonName = $this->config->getAppValue(Application::APP_ID, 'commonName')) {
			$rootCert['commonName'] = $commonName;
			$this->config->deleteAppValue(Application::APP_ID, 'commonName');
		}
		if ($country = $this->config->getAppValue(Application::APP_ID, 'country')) {
			$rootCert['names']['C'] = $country;
			$this->config->deleteAppValue(Application::APP_ID, 'country');
		}
		if ($organization = $this->config->getAppValue(Application::APP_ID, 'organization')) {
			$rootCert['names']['O'] = $organization;
			$this->config->deleteAppValue(Application::APP_ID, 'organization');
		}
		if ($organizationUnit = $this->config->getAppValue(Application::APP_ID, 'organizationUnit')) {
			$rootCert['names']['OU'] = $organizationUnit;
			$this->config->deleteAppValue(Application::APP_ID, 'organizationUnit');
		}
		if ($rootCert) {
			$this->config->setAppValue(Application::APP_ID, 'rootCert', json_encode($rootCert));
		}
	}
}
