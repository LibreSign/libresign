<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version19005Date20261110000000 extends SimpleMigrationStep {
	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('libresign_id_docs')) {
			return null;
		}
		$table = $schema->getTable('libresign_id_docs');
		if (!$table->hasColumn('rejected_at')) {
			$table->addColumn('rejected_at', Types::DATETIME, ['notnull' => false]);
		}
		if (!$table->hasColumn('rejected_by')) {
			$table->addColumn('rejected_by', Types::STRING, ['notnull' => false, 'length' => 64]);
		}
		if (!$table->hasColumn('rejection_reason')) {
			$table->addColumn('rejection_reason', Types::STRING, ['notnull' => false, 'length' => 1024]);
		}
		return $schema;
	}
}
