<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version12000Date20250127160457 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper */
		$schema = $schemaClosure();

		if (!$schema->hasTable('libresign_id_docs')) {
			$table = $schema->createTable('libresign_id_docs');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('sign_request_id', Types::BIGINT, [
				'unsigned' => true,
				'notnull' => false,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'length' => 64,
				'notnull' => false,
			]);
			$table->addColumn('file_type', Types::STRING, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['file_id']);
			$table->addIndex(['sign_request_id']);
			$table->addIndex(['user_id']);
			$table->addUniqueIndex(['sign_request_id', 'user_id', 'file_type'], 'libresign_id_docs_unique');
		}
		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if ($this->db->tableExists('libresign_account_file')) {
			$output->info('Migrating data from libresign_account_file to libresign_id_docs');
			
			$qbSelect = $this->db->getQueryBuilder();
			$qbSelect->select('user_id', 'file_id', 'file_type')
				->from('libresign_account_file');
			
			$result = $qbSelect->executeQuery();
			$migratedCount = 0;
			
			while ($row = $result->fetch()) {
				$qbInsert = $this->db->getQueryBuilder();
				$qbInsert->insert('libresign_id_docs')
					->values([
						'file_id' => $qbInsert->createNamedParameter($row['file_id'], IQueryBuilder::PARAM_INT),
						'user_id' => $qbInsert->createNamedParameter($row['user_id']),
						'file_type' => $qbInsert->createNamedParameter($row['file_type']),
						'sign_request_id' => $qbInsert->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
					]);
				
				try {
					$qbInsert->executeStatement();
					$migratedCount++;
				} catch (\Exception $e) {
					$output->warning('Failed to migrate record: ' . $e->getMessage());
				}
			}
			$result->closeCursor();
			
			$output->info("Migrated $migratedCount records from libresign_account_file to libresign_id_docs");
		}
	}
}
