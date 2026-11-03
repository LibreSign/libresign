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

/** Add an append-only attempt log and a durable, uniquely identified delivery. */
final class Version19002Date20261025000000 extends SimpleMigrationStep {
	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$changed = false;
		if (!$schema->hasTable('libresign_notification_delivery')) {
			$delivery = $schema->createTable('libresign_notification_delivery');
			$delivery->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
			$delivery->addColumn('event_key', Types::STRING, ['length' => 64, 'notnull' => true]);
			$delivery->addColumn('correlation_id', Types::STRING, ['length' => 64, 'notnull' => true]);
			$delivery->addColumn('event_type', Types::STRING, ['length' => 48, 'notnull' => true]);
			$delivery->addColumn('channel', Types::STRING, ['length' => 48, 'notnull' => true]);
			$delivery->addColumn('file_id', Types::INTEGER, ['notnull' => true]);
			$delivery->addColumn('sign_request_id', Types::INTEGER, ['notnull' => false]);
			$delivery->addColumn('owner_id', Types::STRING, ['length' => 64, 'notnull' => true]);
			$delivery->addColumn('recipient_enc', Types::TEXT, ['notnull' => true]);
			$delivery->addColumn('message_enc', Types::TEXT, ['notnull' => false]);
			$delivery->addColumn('state', Types::STRING, ['length' => 32, 'notnull' => true]);
			$delivery->addColumn('next_attempt_at', Types::DATETIME, ['notnull' => false]);
			$delivery->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$delivery->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$delivery->setPrimaryKey(['id']);
			$delivery->addUniqueIndex(['event_key'], 'ls_notify_event_uidx');
			$delivery->addIndex(['correlation_id', 'channel'], 'ls_notify_correl_idx');
			$delivery->addIndex(['file_id', 'created_at'], 'ls_notify_file_idx');
			$delivery->addIndex(['owner_id', 'created_at'], 'ls_notify_owner_idx');
			$delivery->addIndex(['state', 'next_attempt_at'], 'ls_notify_due_idx');
			$delivery->addIndex(['channel', 'created_at'], 'ls_notify_channel_idx');
			$changed = true;
		} else {
			$delivery = $schema->getTable('libresign_notification_delivery');
		}
		if (!$schema->hasTable('libresign_notification_attempt')) {
			$attempt = $schema->createTable('libresign_notification_attempt');
			$attempt->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
			$attempt->addColumn('delivery_id', Types::INTEGER, ['unsigned' => true, 'notnull' => true]);
			$attempt->addColumn('state', Types::STRING, ['length' => 32, 'notnull' => true]);
			$attempt->addColumn('reason_code', Types::STRING, ['length' => 64, 'notnull' => false]);
			$attempt->addColumn('started_at', Types::DATETIME, ['notnull' => true]);
			$attempt->addColumn('finished_at', Types::DATETIME, ['notnull' => false]);
			$attempt->setPrimaryKey(['id']);
			$attempt->addIndex(['delivery_id', 'started_at'], 'ls_notify_attempt_idx');
			$attempt->addIndex(['finished_at'], 'ls_notify_attempt_age_idx');
			$attempt->addForeignKeyConstraint($delivery, ['delivery_id'], ['id'], ['onDelete' => 'CASCADE']);
			$changed = true;
		}
		return $changed ? $schema : null;
	}
}
