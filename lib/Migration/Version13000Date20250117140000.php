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
 * Creates CRL (Certificate Revocation List) table for unique serial number management
 * and RFC-compliant certificate revocation tracking
 */
class Version13000Date20250117140000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('libresign_crl')) {
			$table = $schema->createTable('libresign_crl');

			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'unsigned' => true,
			]);

			$table->addColumn('serial_number', Types::BIGINT, [
				'unsigned' => true,
			]);

			$table->addColumn('owner', Types::STRING, [
				'length' => 255,
			]);

			$table->addColumn('revoked_by', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);

			$table->addColumn('reason_code', Types::SMALLINT, [
				'notnull' => false,
				'unsigned' => true,
			]);

			$table->addColumn('revoked_at', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->addColumn('invalidity_date', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->addColumn('issued_at', Types::DATETIME, [
			]);

			$table->addColumn('valid_to', Types::DATETIME, [
				'notnull' => false,
			]);

			$table->addColumn('status', Types::STRING, [
				'length' => 32,
				'default' => 'issued',
			]);

			$table->addColumn('crl_number', Types::BIGINT, [
				'notnull' => false,
				'unsigned' => true,
			]);

			$table->addColumn('comment', Types::TEXT, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['serial_number'], 'libresign_crl_serial_uk');
			$table->addIndex(['status'], 'libresign_crl_status_idx');
			$table->addIndex(['valid_to'], 'libresign_crl_valid_to_idx');
			$table->addIndex(['reason_code'], 'libresign_crl_reason_code_idx');
		}

		return $schema;
	}
}
