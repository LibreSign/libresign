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

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version2040Date20210619135352 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
		$schema = $schemaClosure();
		$table = $schema->createTable('libresign_file_type');
		$table->addColumn('type', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('name', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('description', Types::TEXT, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->setPrimaryKey(['type']);

		$table = $schema->createTable('libresign_account_file');
		$table->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('file_type', Types::STRING, [
			'notnull' => true,
		]);
		$table->addColumn('file_id', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addIndex(['user_id']);
		$table->addIndex(['file_type']);
		$table->addUniqueIndex(['user_id', 'file_type'], 'libresign_user_file_type_index');

		return $schema;
	}
}
