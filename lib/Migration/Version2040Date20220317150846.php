<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2040Date20220317150846 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper */
		$schema = $schemaClosure();
		$changed = false;

		$tableFileType = $schema->getTable('libresign_file_type');
		if (!$tableFileType->hasPrimaryKey()) {
			$tableFileType->setPrimaryKey(['id']);
			$changed = true;
		}

		$tableAccountFile = $schema->getTable('libresign_account_file');
		if (!$tableAccountFile->hasPrimaryKey()) {
			$tableAccountFile->setPrimaryKey(['user_id', 'file_type']);
			$changed = true;
		}

		if ($changed) {
			return $schema;
		}
		return null;
	}
}
