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

/**
 * Store the signature rejection audit trail: when a signer rejected, and the
 * comment they left, if any.
 */
class Version18004Date20260906000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return ISchemaWrapper|null
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('libresign_sign_request')) {
			return null;
		}

		$table = $schema->getTable('libresign_sign_request');
		$changed = false;

		if (!$table->hasColumn('rejected_at')) {
			$table->addColumn('rejected_at', Types::DATETIME, [
				'notnull' => false,
			]);
			$changed = true;
		}

		if (!$table->hasColumn('rejection_comment')) {
			$table->addColumn('rejection_comment', Types::TEXT, [
				'notnull' => false,
			]);
			$changed = true;
		}

		if (!$table->hasColumn('rejection_comment_private')) {
			$table->addColumn('rejection_comment_private', Types::BOOLEAN, [
				'notnull' => true,
				'default' => false,
			]);
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
