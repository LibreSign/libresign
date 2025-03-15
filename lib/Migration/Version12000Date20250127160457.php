<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version12000Date20250127160457 extends SimpleMigrationStep {

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
			]);
			$table->addColumn('user_id', Types::STRING, [
				'length' => 64,
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
}
