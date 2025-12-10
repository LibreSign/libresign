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

/**
 * Add sequential signing support
 * - Adds 'signing_order', 'status', and 'released_at' columns to libresign_sign_request table
 */
class Version15000Date20251209000000 extends SimpleMigrationStep {
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

		// Add signing order, status, and released_at to SignRequest table
		if ($schema->hasTable('libresign_sign_request')) {
			$tableSignRequest = $schema->getTable('libresign_sign_request');
			if (!$tableSignRequest->hasColumn('signing_order')) {
				$tableSignRequest->addColumn('signing_order', Types::INTEGER, [
					'notnull' => true,
					'default' => 1,
					'comment' => 'Numeric order/stage for sequential signing (e.g., 1, 2, 3)',
				]);
			}
			if (!$tableSignRequest->hasColumn('status')) {
				$tableSignRequest->addColumn('status', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
					'comment' => 'Status: 0=draft, 1=able_to_sign, 2=signed',
				]);
			}
		}

		return $schema;
	}
}
