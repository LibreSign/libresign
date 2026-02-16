<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use OCA\Libresign\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Migrate signature method names from legacy format to new format with Token suffix
 * - signal → signalToken
 * - sms → smsToken
 * - telegram → telegramToken
 * - whatsapp → whatsappToken
 * - xmpp → xmppToken
 *
 * Also migrate files list config keys to use prefixed naming:
 * - grid_view → files_list_grid_view
 * - signer_identify_tab → files_list_signer_identify_tab
 * - sorting_mode → files_list_sorting_mode
 * - sorting_direction → files_list_sorting_direction
 * - filter_modified → files_list_filter_modified
 * - filter_status → files_list_filter_status
 */
class Version17001Date20260210000000 extends SimpleMigrationStep {
	private const LEGACY_MAPPING = [
		'signal' => 'signalToken',
		'sms' => 'smsToken',
		'telegram' => 'telegramToken',
		'whatsapp' => 'whatsappToken',
		'xmpp' => 'xmppToken',
	];

	private const USER_CONFIG_MIGRATIONS = [
		'grid_view' => 'files_list_grid_view',
		'signer_identify_tab' => 'files_list_signer_identify_tab',
		'sorting_mode' => 'files_list_sorting_mode',
		'sorting_direction' => 'files_list_sorting_direction',
		'filter_modified' => 'files_list_filter_modified',
		'filter_status' => 'files_list_filter_status',
	];

	public function __construct(
		private IAppConfig $appConfig,
		private IConfig $config,
		private IDBConnection $db,
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

		$this->migrateUserConfigs($output);
	}

	private function migrateUserConfigs(IOutput $output): void {
		$oldKeys = array_keys(self::USER_CONFIG_MIGRATIONS);
		$query = $this->db->getQueryBuilder();
		$query->selectDistinct('userid')
			->from('preferences')
			->where($query->expr()->eq('appid', $query->createNamedParameter(Application::APP_ID)))
			->andWhere($query->expr()->in('configkey', $query->createNamedParameter($oldKeys, IQueryBuilder::PARAM_STR_ARRAY)));

		$result = $query->executeQuery();
		$userIds = $result->fetchAll(\PDO::FETCH_COLUMN);

		if (empty($userIds)) {
			return;
		}

		$migratedCount = 0;
		$output->info('Migrating files list config keys for ' . count($userIds) . ' users...');

		foreach ($userIds as $userId) {
			foreach (self::USER_CONFIG_MIGRATIONS as $oldKey => $newKey) {
				$oldValue = $this->config->getUserValue($userId, Application::APP_ID, $oldKey, null);
				$newValue = $this->config->getUserValue($userId, Application::APP_ID, $newKey, null);

				// If old key has a value and new key is empty, migrate
				if ($oldValue !== null && $oldValue !== '' && ($newValue === null || $newValue === '')) {
					$this->config->setUserValue($userId, Application::APP_ID, $newKey, $oldValue);
					$this->config->deleteUserValue($userId, Application::APP_ID, $oldKey);
					$migratedCount++;
				} elseif ($oldValue !== null && $oldValue !== '' && $newValue !== null && $newValue !== '') {
					// Both exist, just delete the old one
					$this->config->deleteUserValue($userId, Application::APP_ID, $oldKey);
				}
			}
		}

		if ($migratedCount > 0) {
			$output->info("Migrated $migratedCount config keys to new prefixed format");
		}
	}
}
