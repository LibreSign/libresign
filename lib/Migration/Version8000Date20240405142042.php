<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
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
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\JsonType;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class PostgreSQLJsonType extends JsonType {
	public function getSQLDeclaration(array $column, AbstractPlatform $platform) {
		$return = parent::getSQLDeclaration($column, $platform);
		return implode(' ', [
			$return,
			$column['comment'],
		]);
	}
}

class Version8000Date20240405142042 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('libresign_file');

		$newOptions = [];
		if ($schema->getDatabasePlatform() instanceof PostgreSQLPlatform) {
			$newOptions = [
				'Type' => new PostgreSQLJsonType(),
				'comment' => 'USING to_jsonb(metadata)',
			];
		} else {
			$newOptions = [
				'Type' => new JsonType(),
			];
		}

		if ($table->hasColumn('metadata')) {
			$currentOptions = $table->getColumn('metadata');
			if (!$currentOptions->getType() instanceof JsonType) {
				$table->modifyColumn('metadata', $newOptions);
				$changed = true;
			}
		}

		$table = $schema->getTable('libresign_file_element');
		if ($table->hasColumn('metadata')) {
			$currentOptions = $table->getColumn('metadata');
			if (!$currentOptions->getType() instanceof JsonType) {
				$table->modifyColumn('metadata', $newOptions);
				$changed = true;
			}
		}

		if ($changed) {
			return $schema;
		}

		return null;
	}
}
