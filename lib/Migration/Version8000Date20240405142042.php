<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\ColumnType;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version8000Date20240405142042 extends SimpleMigrationStep {
	private const TABLES = ['libresign_file', 'libresign_file_element'];

	public function __construct(
		private IDBConnection $connection,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Postgres can't auto-cast an existing column to json, that cast is done with raw SQL in postSchemaChange()
		if ($schema->getDatabasePlatform() instanceof PostgreSQLPlatform) {
			return null;
		}

		$changed = false;
		foreach (self::TABLES as $tableName) {
			$table = $schema->getTable($tableName);
			if ($table->hasColumn('metadata') && $table->getColumn('metadata')->getType() !== ColumnType::Json) {
				$table->modifyColumn('metadata', ['type' => 'json']);
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->getDatabasePlatform() instanceof PostgreSQLPlatform) {
			return;
		}

		foreach (self::TABLES as $tableName) {
			$table = $schema->getTable($tableName);
			if ($table->hasColumn('metadata') && $table->getColumn('metadata')->getType() !== ColumnType::Json) {
				$this->connection->executeStatement('ALTER TABLE *PREFIX*' . $tableName . ' ALTER COLUMN metadata TYPE json USING metadata::json');
			}
		}
	}
}
