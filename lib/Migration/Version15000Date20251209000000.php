<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add sequential signing support
 * - Adds 'signing_order', 'status', and 'released_at' columns to libresign_sign_request table
 */
class Version15000Date20251209000000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}
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

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Update existing sign_request records with correct status
		// Only update records that have status = 0 (default)
		// Logic:
		// - If signed IS NOT NULL: status = 2 (SIGNED)
		// - Else if signed IS NULL AND file.status = 1: status = 1 (ABLE_TO_SIGN)
		// - Else: status = 0 (DRAFT) - already set by default

		// First: Update status = 2 for signed requests (signed IS NOT NULL AND status = 0)
		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_sign_request')
			->set('status', $qb->createNamedParameter(2, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('status', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('signed'));
		$qb->executeStatement();

		// Second: Update status = 1 for able_to_sign requests (status = 0 AND signed IS NULL AND file.status = 1)
		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_sign_request', 'sr')
			->innerJoin('sr', 'libresign_file', 'f', $qb->expr()->eq('sr.file_id', 'f.id'))
			->set('sr.status', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('sr.status', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('sr.signed'))
			->andWhere($qb->expr()->eq('f.status', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
