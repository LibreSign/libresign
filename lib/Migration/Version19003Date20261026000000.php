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

/** Add encrypted handoff snapshots to installations with an existing ledger. */
final class Version19003Date20261026000000 extends SimpleMigrationStep {
	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('libresign_notification_delivery')) {
			return null;
		}
		$table = $schema->getTable('libresign_notification_delivery');
		if ($table->hasColumn('message_enc')) {
			return null;
		}
		$table->addColumn('message_enc', Types::TEXT, ['notnull' => false]);
		return $schema;
	}
}
