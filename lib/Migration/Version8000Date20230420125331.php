<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
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

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version8000Date20230420125331 extends SimpleMigrationStep {
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
		$fileUser = $schema->getTable('libresign_file_user');
		if ($fileUser->hasColumn('identify_method')) {
			$fileUser->dropColumn('identify_method');
		}
		$libresignFileUser = $schema->getTable('libresign_file_user');
		if ($libresignFileUser->hasColumn('code')) {
			$libresignFileUser->dropColumn('code');
		}
		if (!$schema->hasTable('libresign_identify_method')) {
			$identifyMethod = $schema->createTable('libresign_identify_method');
			$identifyMethod->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$identifyMethod->addColumn('file_user_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$identifyMethod->addColumn('mandatory', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
				'length' => 1,
			]);
			$identifyMethod->addColumn('code', Types::STRING, [
				'notnull' => false,
				'length' => 256,
			]);
			$identifyMethod->addColumn('identifier_key', Types::STRING, [
				'notnull' => true,
				'length' => 256,
			]);
			$identifyMethod->addColumn('identifier_value', Types::STRING, [
				'notnull' => true,
				'length' => 256,
			]);
			$identifyMethod->addColumn('attempts', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$identifyMethod->addColumn('identified_at_date', Types::DATETIME, [
				'notnull' => false,
				'length' => 20,
				'unsigned' => true,
			]);
			$identifyMethod->addColumn('last_attempt_date', Types::DATETIME, [
				'notnull' => false,
				'length' => 20,
				'unsigned' => true,
			]);
			$identifyMethod->setPrimaryKey(['id'], 'identify_pk_idx');
		} else {
			$table = $schema->getTable('libresign_identify_method');
			$table->dropIndex('identify_method_unique_index');
		}
		return $schema;
	}

	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('libresign_file_user', 'fu')
			->where(
				$query->expr()->nonEmptyString('user_id')
			);
		$result = $query->executeQuery();
		$insert = $this->connection->getQueryBuilder()
			->insert('libresign_identify_method');
		while ($row = $result->fetch()) {
			$insert
				->values([
					'file_user_id' => $insert->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT),
					'mandatory' => $insert->createNamedParameter(1, IQueryBuilder::PARAM_INT),
					'identifier_key' => $insert->createNamedParameter('account'),
					'identifier_value' => $insert->createNamedParameter($row['user_id']),
					'attempts' => $insert->createNamedParameter($row['signed'] ? 1 : 0, IQueryBuilder::PARAM_INT),
					'identified_at_date' => $insert->createNamedParameter($row['signed'] ? new \DateTime('@' . $row['signed']): null, IQueryBuilder::PARAM_DATE),
					'last_attempt_date' => $insert->createNamedParameter($row['signed'] ? new \DateTime('@' . $row['signed']): null, IQueryBuilder::PARAM_DATE),
				])
				->executeStatement();
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('libresign_file_user', 'fu')
			->where(
				$query->expr()->nonEmptyString('email')
			);
		$result = $query->executeQuery();
		$insert = $this->connection->getQueryBuilder()
			->insert('libresign_identify_method');
		while ($row = $result->fetch()) {
			$insert
				->values([
					'file_user_id' => $insert->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT),
					'mandatory' => $insert->createNamedParameter(1, IQueryBuilder::PARAM_INT),
					'identifier_key' => $insert->createNamedParameter('email'),
					'identifier_value' => $insert->createNamedParameter($row['email']),
					'attempts' => $insert->createNamedParameter($row['signed'] ? 1 : 0, IQueryBuilder::PARAM_INT),
					'identified_at_date' => $insert->createNamedParameter($row['signed'] ? new \DateTime('@' . $row['signed']): null, IQueryBuilder::PARAM_DATE),
					'last_attempt_date' => $insert->createNamedParameter($row['signed'] ? new \DateTime('@' . $row['signed']): null, IQueryBuilder::PARAM_DATE),
				])
				->executeStatement();
		}
	}
}
