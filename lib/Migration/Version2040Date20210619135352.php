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
