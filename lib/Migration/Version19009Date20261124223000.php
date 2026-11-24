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

/**
 * Assign each configured remote signing connection to an existing policy
 * scope. Existing connections retain the system scope on upgrade.
 */
final class Version19009Date20261124223000 extends SimpleMigrationStep {
	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('libresign_signing_integration')) {
			return null;
		}
		$table = $schema->getTable('libresign_signing_integration');
		$changed = false;
		if (!$table->hasColumn('scope_type')) {
			$table->addColumn('scope_type', Types::STRING, [
				'length' => 8,
				'notnull' => true,
				'default' => 'system',
				'comment' => 'Policy rule scope of the integration: system, group or user.',
			]);
			$changed = true;
		}
		if (!$table->hasColumn('scope_id')) {
			$table->addColumn('scope_id', Types::STRING, [
				'length' => 255,
				'notnull' => true,
				'default' => '',
				'comment' => 'Group ID or account UID for scoped integrations; empty for system.',
			]);
			$changed = true;
		}
		if (!$table->hasIndex('ls_sign_int_scope_idx')) {
			$table->addIndex(['scope_type', 'scope_id', 'deleted_at', 'id'], 'ls_sign_int_scope_idx');
			$changed = true;
		}
		return $changed ? $schema : null;
	}
}
