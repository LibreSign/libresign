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
 * Final CSC v1 schema, following the integration table introduced by 19006.
 *
 * The signing session, private continuation, audit and attempt tables are
 * created together. The existing integration table gains its policy scope,
 * and a SignRequest is limited to one session regardless of actor identity.
 *
 * Each change is guarded because development installations may already have
 * applied the earlier, unpublished 19007/19008 steps. Existing rows and
 * encrypted credentials must not be discarded on upgrade.
 */
final class Version19009Date20261124223000 extends SimpleMigrationStep {
	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('libresign_signing_integration')) {
			throw new \LogicException('External signing integrations migration 19006 must run first');
		}

		$changed = false;
		if (!$schema->hasTable('libresign_ext_sign_session')) {
			$table = $schema->createTable('libresign_ext_sign_session');
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'unsigned' => true, 'notnull' => true, 'comment' => 'Database primary key']);
			$table->addColumn('public_id', Types::STRING, ['length' => 32, 'notnull' => true, 'comment' => 'Non-secret public session identifier']);
			$table->addColumn('attempt_key', Types::STRING, ['length' => 64, 'notnull' => true, 'comment' => 'Server-side idempotency key digest for the authorized attempt']);
			$table->addColumn('signer_slot', Types::STRING, ['length' => 64, 'notnull' => true, 'comment' => 'Unique actor and SignRequest slot preventing duplicate session creation']);
			$table->addColumn('actor_id', Types::STRING, ['length' => 255, 'notnull' => true, 'comment' => 'Authorized signing actor identifier']);
			$table->addColumn('sign_request_id', Types::INTEGER, ['notnull' => true, 'comment' => 'Authoritative LibreSign SignRequest identifier']);
			$table->addColumn('integration_id', Types::STRING, ['length' => 32, 'notnull' => true, 'comment' => 'Configured integration public identifier']);
			$table->addColumn('integration_revision', Types::INTEGER, ['notnull' => true, 'comment' => 'Pinned integration configuration revision']);
			$table->addColumn('credential_id', Types::STRING, ['length' => 255, 'notnull' => true, 'comment' => 'Selected external signing credential identifier']);
			$table->addColumn('certificate_sha256', Types::STRING, ['length' => 64, 'notnull' => true, 'comment' => 'SHA-256 fingerprint of pinned expected X.509 certificate']);
			$table->addColumn('document_revision', Types::STRING, ['length' => 128, 'notnull' => true, 'comment' => 'Trusted document revision identifier']);
			$table->addColumn('document_sha256', Types::STRING, ['length' => 64, 'notnull' => true, 'comment' => 'SHA-256 fingerprint of exact input PDF bytes']);
			$table->addColumn('payload_version', Types::SMALLINT, ['notnull' => true, 'default' => 1, 'comment' => 'Version of encrypted preparation envelope']);
			$table->addColumn('prepared_enc', Types::TEXT, ['length' => 16777215, 'notnull' => true, 'comment' => 'Encrypted exact SignerPHP cryptographic payload and opaque preparation']);
			$table->addColumn('signature_enc', Types::TEXT, ['length' => 16777215, 'notnull' => false, 'comment' => 'Encrypted external signature bytes awaiting publication']);
			$table->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true, 'comment' => 'Explicit durable signing state']);
			$table->addColumn('revision', Types::INTEGER, ['notnull' => true, 'default' => 1, 'comment' => 'Compare-and-swap session revision']);
			$table->addColumn('expires_at', Types::DATETIME, ['notnull' => true, 'comment' => 'UTC session expiration timestamp']);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true, 'comment' => 'UTC creation timestamp']);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true, 'comment' => 'UTC last mutation timestamp']);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['public_id'], 'ls_ext_ss_public_uidx');
			$table->addUniqueIndex(['attempt_key'], 'ls_ext_ss_attempt_uidx');
			$table->addUniqueIndex(['signer_slot'], 'ls_ext_ss_slot_uidx');
			$table->addUniqueIndex(['sign_request_id'], 'ls_ext_ss_req_uidx');
			$table->addIndex(['actor_id', 'sign_request_id'], 'ls_ext_ss_actor_req_idx');
			$table->addIndex(['status', 'expires_at'], 'ls_ext_ss_expiry_idx');
			$changed = true;
		} else {
			$table = $schema->getTable('libresign_ext_sign_session');
			if (!$table->hasIndex('ls_ext_ss_req_uidx')) {
				// The previous 19008 step added this constraint separately.
				// Duplicate sessions must be reconciled, never silently deleted.
				$table->addUniqueIndex(['sign_request_id'], 'ls_ext_ss_req_uidx');
				$changed = true;
			}
		}

		if (!$schema->hasTable('libresign_ext_sign_private')) {
			$table = $schema->createTable('libresign_ext_sign_private');
			$table->addColumn('session_id', Types::STRING, ['length' => 32, 'notnull' => true, 'comment' => 'Non-secret public session reference']);
			$table->addColumn('secrets_enc', Types::TEXT, ['length' => 16777215, 'notnull' => true, 'comment' => 'Encrypted protocol continuation data and authorization tokens']);
			$table->setPrimaryKey(['session_id']);
			$changed = true;
		}
		if (!$schema->hasTable('libresign_ext_sign_audit')) {
			$table = $schema->createTable('libresign_ext_sign_audit');
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'unsigned' => true, 'notnull' => true, 'comment' => 'Database primary key']);
			$table->addColumn('session_id', Types::STRING, ['length' => 32, 'notnull' => true, 'comment' => 'Non-secret public session reference']);
			$table->addColumn('event', Types::STRING, ['length' => 32, 'notnull' => true, 'comment' => 'Redacted audit event code']);
			$table->addColumn('actor_id', Types::STRING, ['length' => 255, 'notnull' => true, 'comment' => 'Authorized signing actor identifier']);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true, 'comment' => 'UTC creation timestamp']);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['session_id', 'id'], 'ls_ext_ss_audit_idx');
			$changed = true;
		}
		if (!$schema->hasTable('libresign_ext_sign_attempt')) {
			$table = $schema->createTable('libresign_ext_sign_attempt');
			$table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'unsigned' => true, 'notnull' => true, 'comment' => 'Attempt ledger primary key']);
			$table->addColumn('session_id', Types::STRING, ['length' => 32, 'notnull' => true, 'comment' => 'Session claiming this external cryptographic operation']);
			$table->addColumn('operation_id', Types::STRING, ['length' => 32, 'notnull' => true, 'comment' => 'Unique local cryptographic submission identifier']);
			$table->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true, 'comment' => 'Durable outcome classification of submission']);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true, 'comment' => 'UTC submission reservation time']);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true, 'comment' => 'UTC last attempt outcome change']);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['session_id'], 'ls_ext_ss_one_sign_uidx');
			$table->addUniqueIndex(['operation_id'], 'ls_ext_ss_op_uidx');
			$changed = true;
		}

		$integration = $schema->getTable('libresign_signing_integration');
		if (!$integration->hasColumn('scope_type')) {
			$integration->addColumn('scope_type', Types::STRING, [
				'length' => 8,
				'notnull' => true,
				'default' => 'system',
				'comment' => 'Policy rule scope of the integration: system, group or user.',
			]);
			$changed = true;
		}
		if (!$integration->hasColumn('scope_id')) {
			$integration->addColumn('scope_id', Types::STRING, [
				'length' => 255,
				'notnull' => true,
				'default' => '',
				'comment' => 'Group ID or account UID for scoped integrations; empty for system.',
			]);
			$changed = true;
		}
		if (!$integration->hasIndex('ls_sign_int_scope_idx')) {
			$integration->addIndex(['scope_type', 'scope_id', 'deleted_at', 'id'], 'ls_sign_int_scope_idx');
			$changed = true;
		}
		return $changed ? $schema : null;
	}
}
