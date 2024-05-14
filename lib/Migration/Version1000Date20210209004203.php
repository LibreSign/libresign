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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20210209004203 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->createTable('libresign_file');

		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('node_id', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('uuid', Types::STRING, [
			'notnull' => true,
			'length' => 36,
			'default' => '',
		]);
		$table->addColumn('created_at', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
			'unsigned' => true,
		]);
		$table->addColumn('name', Types::STRING, [
			'notnull' => true,
			'length' => 256
		]);
		$table->addColumn('callback', 'string', [
			'notnull' => false,
			'length' => 255,
		]);
		$table->addColumn('enabled', 'smallint', [
			'notnull' => true,
			'length' => 1,
		]);

		$table->setPrimaryKey(['id']);
		$table->addIndex(['node_id']);
		$table->addIndex(['uuid']);
		$table->addUniqueIndex(['uuid'], 'file_uuid_index');

		return $schema;
	}
}
