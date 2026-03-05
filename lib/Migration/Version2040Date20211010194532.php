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
class Version2040Date20211010194532 extends SimpleMigrationStep {
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

		if (!$schema->hasTable('libresign_file_element')) {
			$table = $schema->createTable('libresign_file_element');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('file_user_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
				'unsigned' => true,
			]);
			$table->addColumn('metadata', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('page', Types::SMALLINT, [
				'notnull' => true,
				'default' => 1,
				'unsigned' => true,
			]);
			$table->addColumn('urx', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('ury', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('llx', Types::SMALLINT, [
				'notnull' => false,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('lly', Types::SMALLINT, [
				'notnull' => false,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('created_at', Types::DATETIME_MUTABLE, [
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
		}
		return $schema;
	}
}
