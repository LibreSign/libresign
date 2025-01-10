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

class Version7000Date20221028022904 extends SimpleMigrationStep {
	public function __construct(
		protected IAppConfig $appConfig,
	) {
	}

	/**
	 * The migration Version7000Date20221026003343 generated a wrong array to store the cert optional attibutes following this wrong format:
	 * {"commonName":"Test Company","names":{"C":"BR","O":"Organization","OU":"Organization Unit"}}
	 *
	 * This migration is to convert from the exposed format to this format:
	 * {"commonName":"Test Company","names":[{"id":"C","value":"BR"},{"id":"O","value":"Organization"},{"id":"OU","value":"Organization Unit"}]}
	 */
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$rootCert = $this->appConfig->getValueArray(Application::APP_ID, 'rootCert');
		if (is_array($rootCert) && array_key_exists('names', $rootCert)) {
			$names = [];
			foreach ($rootCert['names'] as $key => $value) {
				if (is_string($key) && is_string($value)) {
					$names[] = [
						'id' => $key,
						'value' => $value,
					];
				}
			}
			if (count($names)) {
				$rootCert['names'] = $names;
				$this->appConfig->setValueArray(Application::APP_ID, 'rootCert', $rootCert);
			}
		}
	}
}
