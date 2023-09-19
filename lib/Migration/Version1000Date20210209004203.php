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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
