<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version17000Date20260106000000 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		if ($schema->hasTable('libresign_file')) {
			$table = $schema->getTable('libresign_file');

			if ($table->hasColumn('node_id')) {
				$table->modifyColumn('node_id', [
					'notnull' => false,
				]);
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}
}
