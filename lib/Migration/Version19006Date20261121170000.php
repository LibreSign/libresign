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
		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
			'comment' => 'Internal numeric key for stable database ordering and pagination.',
		]);
		$table->addColumn('public_id', Types::STRING, [
			'length' => 32,
			'notnull' => true,
			'comment' => 'Opaque stable integration ID exposed to authorized administrators.',
		]);
		$table->addColumn('driver_id', Types::STRING, [
			'length' => 64,
			'notnull' => true,
			'comment' => 'Declarative driver ID, independent of the configured instance.',
		]);
		$table->addColumn('name', Types::STRING, [
			'length' => 128,
			'notnull' => true,
			'comment' => 'Administrator-defined display name for this integration.',
		]);
		$table->addColumn('configuration', Types::TEXT, [
			'notnull' => true,
			'comment' => 'Validated non-secret driver configuration encoded as JSON.',
		]);
		$table->addColumn('secrets_enc', Types::TEXT, [
			'notnull' => true,
			'comment' => 'Secrets encrypted with the Nextcloud ICrypto service; never API output.',
		]);
		$table->addColumn('enabled', Types::SMALLINT, [
			'notnull' => true,
			'default' => 0,
			'comment' => 'Administrative enablement only; distinct from the global experimental gate.',
		]);
		$table->addColumn('revision', Types::INTEGER, [
			'notnull' => true,
			'default' => 1,
			'comment' => 'Optimistic concurrency revision, incremented on each modification.',
		]);
		$table->addColumn('created_at', Types::DATETIME, [
			'notnull' => true,
			'comment' => 'UTC creation timestamp of the configured integration.',
		]);
		$table->addColumn('updated_at', Types::DATETIME, [
			'notnull' => true,
			'comment' => 'UTC timestamp of the last configuration or lifecycle change.',
		]);
		$table->addColumn('deleted_at', Types::DATETIME, [
			'notnull' => false,
			'comment' => 'UTC deletion timestamp; retained tombstones preserve session references.',
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['public_id'], 'ls_sign_int_public_uidx');
		$table->addIndex(['deleted_at', 'id'], 'ls_sign_int_listing_idx');
		$table->addIndex(['driver_id', 'deleted_at'], 'ls_sign_int_driver_idx');
		return $schema;
	}
}
