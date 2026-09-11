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

class Version19000Date20260830200000 extends SimpleMigrationStep {
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
		if ($table->hasColumn('participant_role')) {
			return null;
		}

		$table->addColumn('participant_role', Types::STRING, [
			'notnull' => true,
			'length' => 32,
			'default' => 'signer',
		]);

		return $schema;
	}
}
