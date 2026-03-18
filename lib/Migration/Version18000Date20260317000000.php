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

class Version18000Date20260317000000 extends SimpleMigrationStep {
	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('libresign_permission_set')) {
			$table = $schema->createTable('libresign_permission_set');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('scope_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('enabled', Types::SMALLINT, [
				'notnull' => true,
				'default' => 1,
			]);
			$table->addColumn('priority', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('policy_json', Types::TEXT, [
				'notnull' => true,
				'default' => '{}',
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['scope_type'], 'ls_perm_set_scope_idx');
		}

		if (!$schema->hasTable('libresign_permission_set_binding')) {
			$table = $schema->createTable('libresign_permission_set_binding');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('permission_set_id', Types::INTEGER, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('target_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('target_id', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('created_at', Types::DATETIME, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['permission_set_id'], 'ls_perm_bind_set_idx');
			$table->addUniqueIndex(['target_type', 'target_id'], 'ls_perm_bind_target_uidx');
			$table->addForeignKeyConstraint('libresign_permission_set', ['permission_set_id'], ['id'], [
				'onDelete' => 'CASCADE',
			]);
		}

		return $schema;
	}
}
