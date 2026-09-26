<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Provider\SignerGeolocation\SignerGeolocationPolicy;
use OCA\Libresign\Service\SignerGeolocation\SignerGeolocationMetadataValidator;
use OCA\Libresign\Service\SignerGeolocation\SignerGeolocationPolicyService;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * v15.0.x shipped device geolocation as `signer_geolocation`, with the signer
 * requirement stored as `geolocationRequirement` and the collected payload flat
 * under `geolocation`.
 */
class Version18005Date20260922000000 extends SimpleMigrationStep {
	private const LEGACY_POLICY_KEY = 'signer_geolocation';
	private const LEGACY_METADATA_REQUIREMENT_KEY = 'geolocationRequirement';
	private const DELEGATED_OVERRIDE_SUFFIX = '__delegated_override';
	private const METADATA_BATCH_SIZE = 1000;

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->renameAppConfigKey(self::LEGACY_POLICY_KEY, SignerGeolocationPolicy::SYSTEM_APP_CONFIG_KEY);
		$this->renameAppConfigKey(
			self::LEGACY_POLICY_KEY . '.allow_child_override',
			SignerGeolocationPolicy::SYSTEM_APP_CONFIG_KEY . '.allow_child_override',
		);
		$this->appConfig->clearCache();

		$this->renamePreferenceKey('policy.' . self::LEGACY_POLICY_KEY, 'policy.' . SignerGeolocationPolicy::KEY);
		$this->renamePreferenceKey(
			'policy.' . self::LEGACY_POLICY_KEY . '.assigned',
			'policy.' . SignerGeolocationPolicy::KEY . '.assigned',
		);

		$this->migratePermissionSets();
		$this->migrateJsonMetadata('libresign_file', $this->migrateFileMetadata(...));
		$this->migrateJsonMetadata('libresign_sign_request', $this->migrateSignRequestMetadata(...));
	}

	private function renameAppConfigKey(string $legacyKey, string $newKey): void {
		if (!$this->appConfigRowExists($legacyKey)) {
			return;
		}

		if ($this->appConfigRowExists($newKey)) {
			$delete = $this->connection->getQueryBuilder();
			$delete->delete('appconfig')
				->where($delete->expr()->eq('appid', $delete->createNamedParameter(Application::APP_ID)))
				->andWhere($delete->expr()->eq('configkey', $delete->createNamedParameter($legacyKey)))
				->executeStatement();
			return;
		}

		$update = $this->connection->getQueryBuilder();
		$update->update('appconfig')
			->set('configkey', $update->createNamedParameter($newKey))
			->where($update->expr()->eq('appid', $update->createNamedParameter(Application::APP_ID)))
			->andWhere($update->expr()->eq('configkey', $update->createNamedParameter($legacyKey)))
			->executeStatement();
	}

	private function appConfigRowExists(string $key): bool {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('configkey')
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		try {
			return $result->fetchOne() !== false;
		} finally {
			$result->closeCursor();
		}
	}

	private function renamePreferenceKey(string $legacyKey, string $newKey): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('userid')
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($newKey)));

		$result = $qb->executeQuery();
		$usersWithNewKey = [];
		try {
			while (($userId = $result->fetchOne()) !== false) {
				$usersWithNewKey[] = (string)$userId;
			}
		} finally {
			$result->closeCursor();
		}

		foreach (array_chunk($usersWithNewKey, 1000) as $chunk) {
			$delete = $this->connection->getQueryBuilder();
			$delete->delete('preferences')
				->where($delete->expr()->eq('appid', $delete->createNamedParameter(Application::APP_ID)))
				->andWhere($delete->expr()->eq('configkey', $delete->createNamedParameter($legacyKey)))
				->andWhere($delete->expr()->in('userid', $delete->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)))
				->executeStatement();
		}

		$update = $this->connection->getQueryBuilder();
		$update->update('preferences')
			->set('configkey', $update->createNamedParameter($newKey))
			->where($update->expr()->eq('appid', $update->createNamedParameter(Application::APP_ID)))
			->andWhere($update->expr()->eq('configkey', $update->createNamedParameter($legacyKey)))
			->executeStatement();
	}

	private function migratePermissionSets(): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('id', 'policy_json')
			->from('libresign_permission_set');

		$result = $qb->executeQuery();
		$changes = [];
		try {
			while ($row = $result->fetchAssociative()) {
				$policyJson = json_decode((string)($row['policy_json'] ?? ''), true);
				if (!is_array($policyJson)) {
					continue;
				}

				$migrated = $this->renameArrayKey($policyJson, self::LEGACY_POLICY_KEY, SignerGeolocationPolicy::KEY);
				$migrated = $this->renameArrayKey(
					$migrated,
					self::LEGACY_POLICY_KEY . self::DELEGATED_OVERRIDE_SUFFIX,
					SignerGeolocationPolicy::KEY . self::DELEGATED_OVERRIDE_SUFFIX,
				);
				if ($migrated !== $policyJson) {
					$changes[(int)$row['id']] = $migrated;
				}
			}
		} finally {
			$result->closeCursor();
		}

		foreach ($changes as $id => $policyJson) {
			$update = $this->connection->getQueryBuilder();
			$update->update('libresign_permission_set')
				->set('policy_json', $update->createNamedParameter(json_encode($policyJson, JSON_THROW_ON_ERROR)))
				->where($update->expr()->eq('id', $update->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
				->executeStatement();
		}
	}

	/**
	 * @param Closure(array<string, mixed>): array<string, mixed> $migrate
	 */
	private function migrateJsonMetadata(string $table, Closure $migrate): void {
		$lastId = 0;

		do {
			$qb = $this->connection->getQueryBuilder();
			$qb->select('id', 'metadata')
				->from($table)
				->where($qb->expr()->isNotNull('metadata'))
				->andWhere($qb->expr()->gt(
					'id',
					$qb->createNamedParameter($lastId, IQueryBuilder::PARAM_INT),
				))
				->orderBy('id', 'ASC')
				->setMaxResults(self::METADATA_BATCH_SIZE);

			$result = $qb->executeQuery();
			$fetched = 0;
			$changes = [];
			try {
				while ($row = $result->fetchAssociative()) {
					$id = (int)$row['id'];
					$lastId = $id;
					$fetched++;

					$metadata = json_decode((string)($row['metadata'] ?? ''), true);
					if (!is_array($metadata)) {
						continue;
					}

					$migrated = $migrate($metadata);
					if ($migrated !== $metadata) {
						$changes[$id] = $migrated;
					}
				}
			} finally {
				$result->closeCursor();
			}

			foreach ($changes as $id => $metadata) {
				$update = $this->connection->getQueryBuilder();
				$update->update($table)
					->set('metadata', $update->createNamedParameter($metadata, IQueryBuilder::PARAM_JSON))
					->where($update->expr()->eq('id', $update->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
					->executeStatement();
			}
		} while ($fetched === self::METADATA_BATCH_SIZE);
	}

	/**
	 * @param array<string, mixed> $metadata
	 * @return array<string, mixed>
	 */
	private function migrateFileMetadata(array $metadata): array {
		if (!isset($metadata['policy_snapshot']) || !is_array($metadata['policy_snapshot'])) {
			return $metadata;
		}

		$metadata['policy_snapshot'] = $this->renameArrayKey(
			$metadata['policy_snapshot'],
			self::LEGACY_POLICY_KEY,
			SignerGeolocationPolicy::KEY,
		);
		return $metadata;
	}

	/**
	 * @param array<string, mixed> $metadata
	 * @return array<string, mixed>
	 */
	private function migrateSignRequestMetadata(array $metadata): array {
		$metadata = $this->renameArrayKey(
			$metadata,
			self::LEGACY_METADATA_REQUIREMENT_KEY,
			SignerGeolocationPolicyService::METADATA_REQUIREMENT_KEY,
		);

		$geolocation = $metadata[SignerGeolocationMetadataValidator::METADATA_GEOLOCATION_KEY] ?? null;
		if (is_array($geolocation) && array_key_exists('status', $geolocation)) {
			$metadata[SignerGeolocationMetadataValidator::METADATA_GEOLOCATION_KEY] = [
				SignerGeolocationMetadataValidator::METADATA_DEVICE_KEY => $geolocation,
			];
		}

		return $metadata;
	}

	/**
	 * @param array<array-key, mixed> $values
	 * @return array<array-key, mixed>
	 */
	private function renameArrayKey(array $values, string $legacyKey, string $newKey): array {
		if (!array_key_exists($legacyKey, $values)) {
			return $values;
		}

		if (!array_key_exists($newKey, $values)) {
			$values[$newKey] = $values[$legacyKey];
		}
		unset($values[$legacyKey]);
		return $values;
	}
}
