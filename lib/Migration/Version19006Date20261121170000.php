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
 * Persist distinct configured external signing integrations. Deleted entries are
 * retained as disabled tombstones for future session and audit references.
 */
final class Version19006Date20261121170000 extends SimpleMigrationStep {
	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if ($schema->hasTable('libresign_signing_integration')) {
			return null;
		}

		$table = $schema->createTable('libresign_signing_integration');
		$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('public_id', Types::STRING, ['length' => 32, 'notnull' => true]);
		$table->addColumn('driver_id', Types::STRING, ['length' => 64, 'notnull' => true]);
		$table->addColumn('name', Types::STRING, ['length' => 128, 'notnull' => true]);
		$table->addColumn('configuration', Types::TEXT, ['notnull' => true]);
		$table->addColumn('secrets_enc', Types::TEXT, ['notnull' => true]);
		$table->addColumn('enabled', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
		$table->addColumn('revision', Types::INTEGER, ['notnull' => true, 'default' => 1]);
		$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
		$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
		$table->addColumn('deleted_at', Types::DATETIME, ['notnull' => false]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['public_id'], 'ls_sign_int_public_uidx');
		$table->addIndex(['deleted_at', 'id'], 'ls_sign_int_listing_idx');
		$table->addIndex(['driver_id', 'deleted_at'], 'ls_sign_int_driver_idx');
		return $schema;
	}
}
