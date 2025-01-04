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

class Version11000Date20250103005204 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper */
		$schema = $schemaClosure();

		$tableFile = $schema->getTable('libresign_file');
		if (!$tableFile->hasColumn('signed_hash')) {
			$tableFile->addColumn('signed_hash', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
		}

		$tableSignRequest = $schema->getTable('libresign_sign_request');
		if (!$tableSignRequest->hasColumn('signed_hash')) {
			$tableSignRequest->addColumn('signed_hash', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
		}
		return $schema;
	}
}
