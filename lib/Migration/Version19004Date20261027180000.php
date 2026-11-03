<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCA\Libresign\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Reconcile JSON policy metadata on upgrades where the earlier repair was
 * recorded but the persisted type remained inconsistent.
 *
 * Never rewrite policy values, lazy flags or non-type bits (e.g. sensitivity).
 */
final class Version19004Date20261027180000 extends SimpleMigrationStep {
	private const EXPECTED_TYPES = [
		'add_footer' => IAppConfig::VALUE_STRING,
		'identification_documents' => IAppConfig::VALUE_ARRAY,
		'groups_request_sign' => IAppConfig::VALUE_STRING,
	];

	private const VALUE_TYPE_BITS = IAppConfig::VALUE_MIXED
		| IAppConfig::VALUE_STRING
		| IAppConfig::VALUE_INT
		| IAppConfig::VALUE_FLOAT
		| IAppConfig::VALUE_BOOL
		| IAppConfig::VALUE_ARRAY;

	private const MAX_CONCURRENT_RETRIES = 3;

	public function __construct(
		private IDBConnection $connection,
		private IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		try {
			foreach (self::EXPECTED_TYPES as $key => $expectedType) {
				$this->repairType($key, $expectedType);
			}

			// Do not record a successful migration while valid JSON policies
			// still carry a conflicting type, even after concurrent writes.
			foreach (self::EXPECTED_TYPES as $key => $expectedType) {
				$row = $this->loadRow($key);
				if ($row === null || !is_string($row['configvalue'])
					|| !is_array(json_decode($row['configvalue'], true))) {
					continue;
				}

				$currentType = (int)$row['type'];
				if ($currentType !== (($currentType & ~self::VALUE_TYPE_BITS) | $expectedType)) {
					throw new \RuntimeException('AppConfig type reconciliation incomplete for ' . $key);
				}
			}
		} finally {
			// The DB may also have been changed by a concurrent writer.
			$this->appConfig->clearCache();
		}
	}

	private function repairType(string $key, int $expectedType): void {
		// A lost compare-and-swap must not silently mark this migration done.
		for ($attempt = 0; $attempt <= self::MAX_CONCURRENT_RETRIES; $attempt++) {
			$row = $this->loadRow($key);
			if ($row === null || !is_string($row['configvalue'])) {
				return;
			}

			$originalValue = $row['configvalue'];
			// Only JSON containers are policy values. Leave legacy scalars,
			// opaque/encrypted data and malformed JSON entirely untouched.
			if (!is_array(json_decode($originalValue, true))) {
				return;
			}

			$currentType = (int)$row['type'];
			$correctType = ($currentType & ~self::VALUE_TYPE_BITS) | $expectedType;
			if ($currentType === $correctType) {
				return;
			}

			if ($attempt === self::MAX_CONCURRENT_RETRIES) {
				throw new \RuntimeException('Concurrent AppConfig change prevented metadata repair for ' . $key);
			}

			$update = $this->connection->getQueryBuilder();
			$updated = $update->update('appconfig')
				->set('type', $update->createNamedParameter($correctType, IQueryBuilder::PARAM_INT))
				->where($update->expr()->eq('appid', $update->createNamedParameter(Application::APP_ID)))
				->andWhere($update->expr()->eq('configkey', $update->createNamedParameter($key)))
				->andWhere($update->expr()->eq('type', $update->createNamedParameter($currentType, IQueryBuilder::PARAM_INT)))
				->andWhere($update->expr()->eq('lazy', $update->createNamedParameter((int)$row['lazy'], IQueryBuilder::PARAM_INT)))
				->andWhere($update->expr()->eq('configvalue', $update->createNamedParameter($originalValue)))
				->executeStatement();

			if ($updated === 1) {
				return;
			}
		}
	}

	/** @return array<string, mixed>|null */
	private function loadRow(string $key): ?array {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('configvalue', 'type', 'lazy')
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)));

		$result = $qb->executeQuery();
		try {
			$row = $result->fetchAssociative();
			return $row === false ? null : $row;
		} finally {
			$result->closeCursor();
		}
	}
}
