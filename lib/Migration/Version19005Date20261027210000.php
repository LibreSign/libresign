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
 * Reserve an idempotency key before a direct signing request creates a file.
 * The unique constraint is the concurrency guard across webserver instances.
 */
class Version19005Date20261027210000 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if ($schema->hasTable('libresign_direct_sign')) {
			return null;
		}

		$table = $schema->createTable('libresign_direct_sign');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
		$table->addColumn('user_id', Types::STRING, ['length' => 255, 'notnull' => true]);
		$table->addColumn('request_key', Types::STRING, ['length' => 128, 'notnull' => true]);
		$table->addColumn('fingerprint', Types::STRING, ['length' => 64, 'notnull' => true]);
		$table->addColumn('file_id', Types::BIGINT, ['notnull' => false]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['user_id', 'request_key'], 'libresign_direct_sign_key');
		return $schema;
	}
}
