<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use OCA\Libresign\AppInfo\Application;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migrate signature method names from legacy format to new format with Token suffix
 * - signal → signalToken
 * - sms → smsToken
 * - telegram → telegramToken
 * - whatsapp → whatsappToken
 * - xmpp → xmppToken
 */
class Version17001Date20260210000000 extends SimpleMigrationStep {
	private const LEGACY_MAPPING = [
		'signal' => 'signalToken',
		'sms' => 'smsToken',
		'telegram' => 'telegramToken',
		'whatsapp' => 'whatsappToken',
		'xmpp' => 'xmppToken',
	];

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$identifyMethods = $this->appConfig->getValueArray(Application::APP_ID, 'identify_methods', []);

		if (!is_array($identifyMethods) || empty($identifyMethods)) {
			return;
		}

		$updated = false;
		foreach ($identifyMethods as &$identifyMethod) {
			// Update signatureMethods keys
			if (isset($identifyMethod['signatureMethods']) && is_array($identifyMethod['signatureMethods'])) {
				$newSignatureMethods = [];
				foreach ($identifyMethod['signatureMethods'] as $key => $value) {
					$newKey = self::LEGACY_MAPPING[$key] ?? $key;
					$newSignatureMethods[$newKey] = $value;
					if ($newKey !== $key) {
						$updated = true;
					}
				}
				$identifyMethod['signatureMethods'] = $newSignatureMethods;
			}

			// Update signatureMethodEnabled array
			if (isset($identifyMethod['signatureMethodEnabled']) && is_array($identifyMethod['signatureMethodEnabled'])) {
				$identifyMethod['signatureMethodEnabled'] = array_map(
					function ($methodName) use (&$updated) {
						$newName = self::LEGACY_MAPPING[$methodName] ?? $methodName;
						if ($newName !== $methodName) {
							$updated = true;
						}
						return $newName;
					},
					$identifyMethod['signatureMethodEnabled']
				);
			}

			// Update availableSignatureMethods array
			if (isset($identifyMethod['availableSignatureMethods']) && is_array($identifyMethod['availableSignatureMethods'])) {
				$identifyMethod['availableSignatureMethods'] = array_map(
					function ($methodName) use (&$updated) {
						$newName = self::LEGACY_MAPPING[$methodName] ?? $methodName;
						if ($newName !== $methodName) {
							$updated = true;
						}
						return $newName;
					},
					$identifyMethod['availableSignatureMethods']
				);
			}
		}

		if ($updated) {
			$this->appConfig->setValueArray(Application::APP_ID, 'identify_methods', $identifyMethods);
			$output->info('Updated signature method names to new format with Token suffix');
		}
	}
}
