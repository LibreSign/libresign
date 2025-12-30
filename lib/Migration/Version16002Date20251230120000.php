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
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version16002Date20251230120000 extends SimpleMigrationStep {
	protected IAppData $appData;

	public function __construct(
		private IDBConnection $connection,
		private IAppDataFactory $appDataFactory,
	) {
		$this->appData = $appDataFactory->get('libresign');
	}

	#[\Override]
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->backupUserElementTable();
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		if ($schema->hasTable('libresign_user_element')) {
			$table = $schema->getTable('libresign_user_element');
			if (!$table->hasColumn('node_id')) {
				$table->addColumn('node_id', Types::BIGINT, [
					'notnull' => false,
					'unsigned' => true,
				]);
				$changed = true;
			}

			if ($table->hasColumn('file_id')) {
				$table->dropColumn('file_id');
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}

	private function backupUserElementTable(): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('*')
			->from('libresign_user_element')
			->orderBy('id');

		$cursor = $qb->executeQuery();
		$row = $cursor->fetch();
		if (!$row) {
			$cursor->closeCursor();
			return;
		}

		$folder = $this->appData->getFolder('/');
		$file = $folder->newFile('backup-table-libresign_user_element_Version16002Date20251230120000.csv');
		$fp = $file->write();

		fputcsv($fp, array_keys($row));
		fputcsv($fp, $row);
		while ($row = $cursor->fetch()) {
			fputcsv($fp, $row);
		}

		fclose($fp);
		$cursor->closeCursor();
	}

	private function restoreNodeIdsFromBackup(): void {
		$folder = $this->appData->getFolder('/');
		$filename = 'backup-table-libresign_user_element_Version16002Date20251230120000.csv';
		if (!$folder->fileExists($filename)) {
			return;
		}

		$file = $folder->getFile($filename);
		$handle = $file->read();
		if ($handle === false) {
			return;
		}

		$header = fgetcsv($handle);
		if ($header === false) {
			fclose($handle);
			return;
		}

		$columnIndex = array_flip($header);
		if (!isset($columnIndex['id']) || !isset($columnIndex['file_id'])) {
			fclose($handle);
			return;
		}

		while (($row = fgetcsv($handle)) !== false) {
			if (!isset($row[$columnIndex['id']])) {
				continue;
			}

			$userElementId = (int) $row[$columnIndex['id']];
			$nodeId = $row[$columnIndex['file_id']] ?? null;
			if ($nodeId === null || $nodeId === '') {
				continue;
			}

			$qb = $this->connection->getQueryBuilder();
			$qb->update('libresign_user_element')
				->set('node_id', $qb->createNamedParameter((int) $nodeId, IQueryBuilder::PARAM_INT))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($userElementId, IQueryBuilder::PARAM_INT)));

			$qb->executeStatement();
		}

		fclose($handle);
	}

	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->restoreNodeIdsFromBackup();
	}

}