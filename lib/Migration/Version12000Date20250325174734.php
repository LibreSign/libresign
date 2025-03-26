<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version12000Date20250325174734 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$tableFile = $schema->getTable('libresign_file');
		if ($tableFile->hasColumn('created_at')) {
			$tableFile->modifyColumn('created_at', [
				'notnull' => true,
			]);
		}

		$tableSignRequest = $schema->getTable('libresign_sign_request');
		if ($tableSignRequest->hasColumn('created_at')) {
			$tableSignRequest->modifyColumn('created_at', [
				'notnull' => true,
			]);
		}
		return $schema;
	}
}
