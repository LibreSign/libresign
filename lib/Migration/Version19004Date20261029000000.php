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

/** Persist the retry counter independently of the optional attempt-history retention. */
final class Version19004Date20261029000000 extends SimpleMigrationStep {
	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('libresign_notification_delivery')) {
			return null;
		}
		$table = $schema->getTable('libresign_notification_delivery');
		if ($table->hasColumn('attempt_count')) {
			return null;
		}
		$table->addColumn('attempt_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		return $schema;
	}
}
