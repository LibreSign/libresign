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
	#[\Override]
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
