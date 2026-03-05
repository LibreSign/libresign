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
	#[\Override]
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$rootCert = $this->appConfig->getValueString(Application::APP_ID, 'rootCert');
		$rootCert = json_decode($rootCert, true);
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
