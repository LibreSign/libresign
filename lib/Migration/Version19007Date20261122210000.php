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
 * Authoritative external-signing sessions. Payloads and private continuation
 * data are encrypted separately; no plaintext PDF or token is indexed.
 */
final class Version19007Date20261122210000 extends SimpleMigrationStep {
	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('libresign_ext_sign_session')) {
			$table = $schema->createTable('libresign_ext_sign_session');
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
			$table->addColumn('public_id', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('actor_id', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('sign_request_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('integration_id', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('integration_revision', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('credential_id', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('certificate_sha256', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('document_revision', Types::STRING, ['length' => 128, 'notnull' => true]);
			$table->addColumn('document_sha256', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('payload_version', Types::SMALLINT, ['notnull' => true, 'default' => 1]);
			$table->addColumn('prepared_enc', Types::TEXT, ['length' => 16777215, 'notnull' => true]);
			$table->addColumn('signature_enc', Types::TEXT, ['length' => 16777215, 'notnull' => false]);
			$table->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('revision', Types::INTEGER, ['notnull' => true, 'default' => 1]);
			$table->addColumn('expires_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['public_id'], 'ls_ext_ss_public_uidx');
			$table->addIndex(['actor_id', 'sign_request_id'], 'ls_ext_ss_actor_req_idx');
			$table->addIndex(['status', 'expires_at'], 'ls_ext_ss_expiry_idx');
		}
		if (!$schema->hasTable('libresign_ext_sign_private')) {
			$table = $schema->createTable('libresign_ext_sign_private');
			$table->addColumn('session_id', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('secrets_enc', Types::TEXT, ['length' => 16777215, 'notnull' => true]);
			$table->setPrimaryKey(['session_id']);
		}
		if (!$schema->hasTable('libresign_ext_sign_audit')) {
			$table = $schema->createTable('libresign_ext_sign_audit');
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
			$table->addColumn('session_id', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('event', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('actor_id', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['session_id', 'id'], 'ls_ext_ss_audit_idx');
		}
		return $schema;
	}
}
