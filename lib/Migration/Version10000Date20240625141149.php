<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version10000Date20240625141149 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		$tableIdentifyMethod = $schema->getTable('libresign_identify_method');
		if (!$tableIdentifyMethod->hasColumn('metadata')) {
			$changed = true;
			$tableIdentifyMethod->addColumn('metadata', Types::JSON, [
				'notnull' => false,
			]);
		}

		$tableUserElement = $schema->getTable('libresign_user_element');
		if (!$tableUserElement->hasColumn('metadata')) {
			$changed = true;
			$tableUserElement->addColumn('metadata', Types::JSON, [
				'notnull' => false,
			]);
		}

		if ($changed) {
			return $schema;
		}
		return null;
	}
}
