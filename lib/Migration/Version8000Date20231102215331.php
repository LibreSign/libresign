<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OC\DB\Exceptions\DbalException;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version8000Date20231102215331 extends SimpleMigrationStep {
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
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper */
		$schema = $schemaClosure();

		if ($schema->hasTable('libresign_file_element')) {
			$table = $schema->getTable('libresign_file_element');
			if ($table->hasColumn('file_user_id') && !$table->hasColumn('sign_request_id')) {
				$table->addColumn('sign_request_id', Types::BIGINT, [
					'notnull' => false,
					'unsigned' => true,
				]);
			}
		}
		if ($schema->hasTable('libresign_identify_method')) {
			$table = $schema->getTable('libresign_identify_method');
			if ($table->hasColumn('file_user_id') && !$table->hasColumn('sign_request_id')) {
				$table->addColumn('sign_request_id', Types::BIGINT, [
					'notnull' => false,
					'unsigned' => true,
				]);
			}
		}

		$table = $schema->getTable('libresign_identify_method');
		if ($table->hasIndex('identify_method_unique_index')) {
			$table->dropIndex('identify_method_unique_index');
		}
		$table->addUniqueIndex(['sign_request_id', 'identifier_key', 'identifier_value'], 'identify_method_unique_index');

		if (!$schema->hasTable('libresign_sign_request')) {
			$table = $schema->createTable('libresign_sign_request');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('uuid', Types::STRING, [
				'notnull' => true,
				'length' => 36,
				'default' => '',
			]);
			$table->addColumn('display_name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('signed', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('metadata', Types::JSON, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['uuid']);
			$table->addUniqueIndex(['uuid'], 'sign_request_uuid_index');
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qbFetch = $this->connection->getQueryBuilder();
		$qbRestore = $this->connection->getQueryBuilder();
		$qbFetch->select('fu.id', 'fu.file_id', 'fu.uuid', 'fu.display_name', 'fu.description', 'fu.created_at', 'fu.signed', 'fu.metadata')
			->from('libresign_file_user', 'fu')
			->leftJoin('fu', 'libresign_sign_request', 'sr', 'fu.id = sr.id')
			->where('sr.id IS NULL');
		$cursor = $qbFetch->executeQuery();
		while ($row = $cursor->fetch()) {
			$qbRestore->insert('libresign_sign_request')
				->values([
					'id' => $qbRestore->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT),
					'file_id' => $qbRestore->createNamedParameter($row['file_id'], IQueryBuilder::PARAM_INT),
					'uuid' => $qbRestore->createNamedParameter($row['uuid']),
					'display_name' => $qbRestore->createNamedParameter($row['display_name']),
					'description' => $qbRestore->createNamedParameter($row['description']),
					'created_at' => $qbRestore->createNamedParameter($row['created_at'], IQueryBuilder::PARAM_INT),
					'signed' => $qbRestore->createNamedParameter($row['signed'], IQueryBuilder::PARAM_INT),
					'metadata' => $qbRestore->createNamedParameter($row['metadata']),
				]);
			$qbRestore->executeStatement();
		}
		$cursor->closeCursor();

		try {
			$qb = $this->connection->getQueryBuilder();
			$qb->update('libresign_file_element')
				->set('sign_request_id', 'file_user_id')
				->executeStatement();
		} catch (DbalException $e) {
		}

		try {
			$qb = $this->connection->getQueryBuilder();
			$qb->update('libresign_identify_method')
				->set('sign_request_id', 'file_user_id')
				->executeStatement();
		} catch (DbalException) {
		}
	}
}
