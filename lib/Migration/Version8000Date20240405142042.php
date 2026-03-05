<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
		$isCreateTable = array_filter($backtrace, fn ($step) => in_array($step['function'], ['_getCreateTableSQL', 'getCreateTablesSQL']));
		if ($isCreateTable) {
			return $return;
		}
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
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('libresign_file');

		$newOptions = [];
		if ($schema->getDatabasePlatform() instanceof PostgreSQLPlatform) {
			$newOptions = [
				'Type' => new PostgreSQLJsonType(),
				'comment' => 'USING metadata::json',
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
