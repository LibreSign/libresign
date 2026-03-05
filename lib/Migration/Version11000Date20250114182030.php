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
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version11000Date20250114182030 extends SimpleMigrationStep {
	protected IAppData $appData;
	public function __construct(
		private IDBConnection $connection,
		private IAppDataFactory $appDataFactory,
	) {
		$this->appData = $appDataFactory->get('libresign');
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// BACKUP BEGIN
		$qb1 = $this->connection->getQueryBuilder();
		$qb1
			->select('lim.*')
			->addSelect('lsr.id as fixed_id')
			->from('libresign_identify_method', 'lim')
			->leftJoin('lim', 'libresign_sign_request', 'lsr',
				$qb1->expr()->andX(
					$qb1->expr()->eq('lsr.file_id', 'lim.sign_request_id'),
					$qb1->expr()->eq('lim.identified_at_date', 'lim.last_attempt_date'),
					$qb1->expr()->neq('lim.sign_request_id', 'lsr.id'),
					$qb1->expr()->eq($qb1->createFunction($this->convertToEpoch('lim.last_attempt_date')), 'lsr.signed')
				)
			)
			->orderBy('lim.id');
		$result = $qb1->executeQuery();
		$row = $result->fetch();
		if (!$row) {
			return;
		}
		$folder = $this->appData->getFolder('/');
		$file = $folder->newFile('backup-table-libresign_identify_method.csv');
		$maxId = 0;
		$fp = $file->write();
		$identifyMethods[] = $row;
		fputcsv($fp, array_keys($row));
		fputcsv($fp, $row);
		while ($row = $result->fetch()) {
			$identifyMethods[] = $row;
			fputcsv($fp, $row);
			if ($row['fixed_id']) {
				if ($row['id'] > $maxId) {
					$maxId = $row['id'];
				}
			}
		}
		fclose($fp);
		$result->closeCursor();
		if ($maxId === 0) {
			$file->delete();
			return;
		}
		// BACKUP END

		// Delete bad rows
		$delete1 = $this->connection->getQueryBuilder();
		$delete1
			->delete('libresign_identify_method')
			->where($delete1->expr()->lte('id', $delete1->createNamedParameter($maxId, IQueryBuilder::PARAM_INT)));
		$delete1->executeStatement();

		// Insert fixed rows
		$insert1 = $this->connection->getQueryBuilder();
		$fixedConstraints = [];
		$fixedIds = [];
		$fixedSignRequestIds = [];
		foreach ($identifyMethods as $key => $row) {
			if (!$row['fixed_id'] || $row['id'] > $maxId || in_array($row['id'], $fixedIds)) {
				continue;
			}
			$fixedIds[] = $row['id'];
			$fixedSignRequestIds[] = $row['fixed_id'];
			$constraint = $row['fixed_id'] . ',' . $row['identifier_key'] . ',' . $row['identifier_value'];
			$fixedConstraints[] = $constraint;
			$insert1
				->insert('libresign_identify_method')
				->values([
					'id' => $insert1->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT),
					'mandatory' => $insert1->createNamedParameter($row['mandatory'], IQueryBuilder::PARAM_INT),
					'code' => $insert1->createNamedParameter(!empty($row['code']) ? $row['code'] : null),
					'identifier_key' => $insert1->createNamedParameter($row['identifier_key']),
					'identifier_value' => $insert1->createNamedParameter($row['identifier_value']),
					'attempts' => $insert1->createNamedParameter($row['attempts'], IQueryBuilder::PARAM_INT),
					'identified_at_date' => $insert1->createNamedParameter(!empty($row['identified_at_date']) ? $row['identified_at_date'] : null),
					'last_attempt_date' => $insert1->createNamedParameter(!empty($row['last_attempt_date']) ? $row['last_attempt_date'] : null),
					'sign_request_id' => $insert1->createNamedParameter($row['fixed_id'], IQueryBuilder::PARAM_INT),
					'metadata' => $insert1->createNamedParameter(!empty($row['metadata']) ? $row['metadata'] : null, IQueryBuilder::PARAM_JSON),
				])
				->executeStatement();
			unset($identifyMethods[$key]);
		}

		// Insert non fixed rows
		$insertedNonFixed = [];
		foreach ($identifyMethods as $key => $row) {
			if ($row['fixed_id'] || $row['id'] > $maxId || in_array($row['id'], $fixedIds) || in_array($row['sign_request_id'], $fixedSignRequestIds)) {
				continue;
			}
			$constraint = $row['sign_request_id'] . ',' . $row['identifier_key'] . ',' . $row['identifier_value'];
			if (in_array($constraint, $fixedConstraints)) {
				continue;
			}
			$insertedNonFixed[] = $constraint;
			$insert1
				->insert('libresign_identify_method')
				->values([
					'id' => $insert1->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT),
					'mandatory' => $insert1->createNamedParameter($row['mandatory'], IQueryBuilder::PARAM_INT),
					'code' => $insert1->createNamedParameter(!empty($row['code']) ? $row['code'] : null),
					'identifier_key' => $insert1->createNamedParameter($row['identifier_key']),
					'identifier_value' => $insert1->createNamedParameter($row['identifier_value']),
					'attempts' => $insert1->createNamedParameter($row['attempts'], IQueryBuilder::PARAM_INT),
					'identified_at_date' => $insert1->createNamedParameter(!empty($row['identified_at_date']) ? $row['identified_at_date'] : null),
					'last_attempt_date' => $insert1->createNamedParameter(!empty($row['last_attempt_date']) ? $row['last_attempt_date'] : null),
					'sign_request_id' => $insert1->createNamedParameter($row['sign_request_id'], IQueryBuilder::PARAM_INT),
					'metadata' => $insert1->createNamedParameter(!empty($row['metadata']) ? $row['metadata'] : null, IQueryBuilder::PARAM_JSON),
				])
				->executeStatement();
			unset($identifyMethods[$key]);
		}
	}

	private function convertToEpoch(string $datetime): string {
		return match ($this->connection->getDatabaseProvider()) {
			IDBConnection::PLATFORM_MYSQL => "UNIX_TIMESTAMP($datetime)",
			IDBConnection::PLATFORM_POSTGRES => "EXTRACT(EPOCH FROM $datetime)",
			IDBConnection::PLATFORM_ORACLE => "(CAST((TO_DATE($datetime, 'YYYY-MM-DD HH24:MI:SS') - TO_DATE('1970-01-01', 'YYYY-MM-DD')) * 86400 AS NUMBER))",
			IDBConnection::PLATFORM_SQLITE => "STRFTIME('%s', $datetime)",
			default => $datetime,
		};
	}
}
