<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version8000Date20231103003922 extends SimpleMigrationStep {
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

		$table = $schema->getTable('libresign_file_element');
		$table->modifyColumn('sign_request_id', [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table = $schema->getTable('libresign_identify_method');
		$table->modifyColumn('sign_request_id', [
			'notnull' => true,
			'unsigned' => true,
		]);

		$table = $schema->getTable('libresign_identify_method');
		if (!$table->hasIndex('identify_method_unique_index')) {
			$table->addUniqueIndex(['sign_request_id', 'identifier_key', 'identifier_value'], 'identify_method_unique_index');
		}

		$table = $schema->getTable('libresign_identify_method');
		if ($table->hasColumn('file_user_id')) {
			$table->dropColumn('file_user_id');
		}

		$table = $schema->getTable('libresign_file_element');
		if ($table->hasColumn('file_user_id')) {
			$table->dropColumn('file_user_id');
		}

		$table = $schema->getTable('libresign_sign_request');
		if (!$table->hasIndex('sign_request_uuid_index')) {
			$table->addUniqueIndex(['uuid'], 'sign_request_uuid_index');
		}

		if ($schema->hasTable('libresign_file_user')) {
			$schema->dropTable('libresign_file_user');
		}

		return $schema;
	}
}
