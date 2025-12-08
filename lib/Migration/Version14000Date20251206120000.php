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

class Version14000Date20251206120000 extends SimpleMigrationStep {
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

		if ($schema->hasTable('libresign_sign_request')) {
			$tableSignRequest = $schema->getTable('libresign_sign_request');
			if (!$tableSignRequest->hasColumn('docmdp_level')) {
				$tableSignRequest->addColumn('docmdp_level', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
					'comment' => 'DocMDP permission level: 0=none, 1=no changes, 2=form fill, 3=form fill + annotations',
				]);
			}
		}

		if ($schema->hasTable('libresign_file')) {
			$tableFile = $schema->getTable('libresign_file');
			if (!$tableFile->hasColumn('modification_status')) {
				$tableFile->addColumn('modification_status', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
					'comment' => 'DocMDP modification detection status: 0=unchecked, 1=unmodified, 2=allowed, 3=violation',
				]);
			}
		}

		return $schema;
	}
}
