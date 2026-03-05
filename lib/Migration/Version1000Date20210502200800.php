<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20210502200800 extends SimpleMigrationStep {
	/** @var IDBConnection */
	protected $connection;
	private array $rows = [];

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	#[\Override]
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->select('id', 'file_id2')
			->from('libresign_file_user', 'fu');
		$this->rows = $query->executeQuery()->fetchAll();
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('libresign_file_user');

		$table->dropColumn('file_id2');

		$table->addColumn('file_id', Types::BIGINT, [
			'notnull' => false,
			'length' => 11,
			'unsigned' => true,
		]);

		return $schema;
	}

	#[\Override]
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		foreach ($this->rows as $row) {
			$query = $this->connection->getQueryBuilder();
			$query
				->update('libresign_file_user')
				->set('file_id', $query->createNamedParameter((int)$row['file_id2']))
				->where($query->expr()->eq('id', $query->createNamedParameter($row['id'])));

			$query->executeQuery();
		}
	}
}
