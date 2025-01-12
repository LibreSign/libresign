<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Your name <your@email.com>
 *
 * @author Your name <your@email.com>
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

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version7000Date20230327094452 extends SimpleMigrationStep {
	public function __construct(
		protected IAppConfig $appConfig,
	) {
	}

	/**
	 * This migration is to fix the rootCert to the new format
	 *
	 * {"commonName":"Test Company","names":{"C":"BR","O":"Organization","OU":"Organization Unit"}}
	 */
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$rootCert = $this->appConfig->getValueString(Application::APP_ID, 'rootCert');
		$rootCert = json_decode($rootCert, true);
		if (is_array($rootCert) && array_key_exists('names', $rootCert)) {
			$names = [];
			foreach ($rootCert['names'] as $value) {
				if (is_array($value) && array_key_exists('id', $value) && array_key_exists('value', $value)) {
					$names[$value['id']] = [
						'value' => $value['value'],
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
