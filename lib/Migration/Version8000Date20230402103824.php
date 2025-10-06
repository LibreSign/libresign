<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version8000Date20230402103824 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper */
		$schema = $schemaClosure();
		$table = $schema->getTable('libresign_file_user');
		if (!$table->hasColumn('identify_method')) {
			$table->addColumn('identify_method', Types::STRING, [
				'notnull' => true,
				'default' => 'account',
				'length' => 30,
			]);
			return $schema;
		}
		return null;
	}
}
