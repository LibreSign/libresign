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
 * A SignRequest has at most one external-signing session regardless of the
 * actor's login/guest identity or the submitted idempotency attempt ID.
 *
 * The earlier actor-bound signer_slot remains for confidential session
 * recovery; this independent database uniqueness constraint protects the
 * SignRequest when the same signer switches authentication contexts.
 * Existing duplicate sessions must be reconciled explicitly, not deleted
 * by the schema upgrade.
 */
final class Version19008Date20261123230000 extends SimpleMigrationStep {
	/** @param Closure(): ISchemaWrapper $schemaClosure */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('libresign_ext_sign_session')) {
			throw new \LogicException('External signing sessions migration 19007 must run first');
		}
		$table = $schema->getTable('libresign_ext_sign_session');
		if (!$table->hasIndex('ls_ext_ss_req_uidx')) {
			$table->addUniqueIndex(['sign_request_id'], 'ls_ext_ss_req_uidx');
		}
		return $schema;
	}
}
