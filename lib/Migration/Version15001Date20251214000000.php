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

/**
 * Add DocMDP level support per file
 * - Adds 'docmdp_level' column to libresign_file table to store DocMDP certification level per file
 */
class Version15001Date20251214000000 extends SimpleMigrationStep {
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

		if ($schema->hasTable('libresign_file')) {
			$tableFile = $schema->getTable('libresign_file');
			if (!$tableFile->hasColumn('docmdp_level')) {
				$tableFile->addColumn('docmdp_level', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
					'comment' => 'DocMDP permission level for this file: 0=none, 1=no changes, 2=form fill, 3=form fill + annotations',
				]);
			}
		}

		return $schema;
	}
}
