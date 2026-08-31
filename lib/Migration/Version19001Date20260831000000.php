<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Drop orphan participant_profile_id column left by an experimental local migration.
 */
class Version19001Date20260831000000 extends SimpleMigrationStep {
	/**
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('libresign_sign_request')) {
			return null;
		}

		$table = $schema->getTable('libresign_sign_request');
		if (!$table->hasColumn('participant_profile_id')) {
			return null;
		}

		$table->dropColumn('participant_profile_id');

		return $schema;
	}
}
