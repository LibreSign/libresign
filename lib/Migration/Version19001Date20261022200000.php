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
 * Repair JSON policies that older LibreSign versions stored with wrong
 * AppConfig type metadata. Keep the original configvalue and lazy flag.
 */
class Version19001Date20261022200000 extends SimpleMigrationStep {
	private const EXPECTED_TYPES = [
		'add_footer' => IAppConfig::VALUE_STRING,
		'identification_documents' => IAppConfig::VALUE_ARRAY,
		'groups_request_sign' => IAppConfig::VALUE_STRING,
	];

	public function __construct(
		private IDBConnection $connection,
		private IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$repaired = 0;

		foreach (self::EXPECTED_TYPES as $key => $expectedType) {
			$qb = $this->connection->getQueryBuilder();
			$qb->select('configvalue', 'type')
				->from('appconfig')
				->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
				->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)));

			$result = $qb->executeQuery();
			try {
				$row = $result->fetchAssociative();
			} finally {
				$result->closeCursor();
			}

			if ($row === false
				|| (int)$row['type'] === $expectedType
				|| !is_string($row['configvalue'])
				|| !is_array(json_decode($row['configvalue'], true))
			) {
				continue;
			}

			$update = $this->connection->getQueryBuilder();
			$update->update('appconfig')
				->set('type', $update->createNamedParameter($expectedType, IQueryBuilder::PARAM_INT))
				->where($update->expr()->eq('appid', $update->createNamedParameter(Application::APP_ID)))
				->andWhere($update->expr()->eq('configkey', $update->createNamedParameter($key)))
				->andWhere($update->expr()->eq('type', $update->createNamedParameter((int)$row['type'], IQueryBuilder::PARAM_INT)))
				->andWhere($update->expr()->eq('configvalue', $update->createNamedParameter($row['configvalue'])));
			$repaired += $update->executeStatement();
		}

		if ($repaired > 0) {
			$this->appConfig->clearCache();
		}
	}

}
