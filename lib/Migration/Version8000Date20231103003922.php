<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
