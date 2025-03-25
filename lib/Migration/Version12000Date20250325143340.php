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

class Version12000Date20250325143340 extends SimpleMigrationStep {
	protected IAppData $appData;
	public function __construct(
		protected IDBConnection $connection,
		private IAppDataFactory $appDataFactory,
	) {
		$this->appData = $appDataFactory->get('libresign');
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->select('id')
			->addSelect('created_at')
			->from('libresign_file');
		$this->persitsData($query, 'backup-table-libresign_file_Version12000Date20250325143340.csv');

		$query = $this->connection->getQueryBuilder();
		$query->select('id')
			->addSelect('created_at')
			->addSelect('signed')
			->from('libresign_sign_request');
		$this->persitsData($query, 'backup-table-libresign_sign_request_Version12000Date20250325143340.csv');
	}

	private function persitsData(IQueryBuilder $query, string $filename): void {
		$cursor = $query->executeQuery();
		$row = $cursor->fetch();
		if ($row) {
			$folder = $this->appData->getFolder('/');
			$file = $folder->newFile($filename);
			$file->putContent('');
			$handle = $file->write();

			fputcsv($handle, array_keys($row));
			fputcsv($handle, $row);
			while ($row = $cursor->fetch()) {
				fputcsv($handle, $row);
			}
		}
		$cursor->closeCursor();
	}

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
			$tableFile->dropColumn('created_at');
		}

		$tableSignRequest = $schema->getTable('libresign_sign_request');
		if ($tableSignRequest->hasColumn('created_at')) {
			$tableSignRequest->dropColumn('created_at');
		}
		if ($tableSignRequest->hasColumn('signed')) {
			$tableSignRequest->dropColumn('signed');
		}
		return $schema;
	}
}
