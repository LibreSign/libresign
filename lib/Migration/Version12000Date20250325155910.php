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
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * FIXME Auto-generated migration step: Please modify to your needs!
 */
class Version12000Date20250325155910 extends SimpleMigrationStep {
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
	 * @return null|ISchemaWrapper
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$tableFile = $schema->getTable('libresign_file');
		if (!$tableFile->hasColumn('created_at')) {
			$tableFile->addColumn('created_at', Types::DATETIME, [
				'notnull' => false,
			]);
		}

		$tableSignRequest = $schema->getTable('libresign_sign_request');
		if (!$tableSignRequest->hasColumn('created_at')) {
			$tableSignRequest->addColumn('created_at', Types::DATETIME, [
				'notnull' => false,
			]);
		}
		if (!$tableSignRequest->hasColumn('signed')) {
			$tableSignRequest->addColumn('signed', Types::DATETIME, [
				'notnull' => false,
			]);
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
		$this->updateFile();
		$this->updateSignRequest();
	}

	private function updateFile(): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('libresign_file')
			->set('created_at', $update->createParameter('created_at'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		try {
			$folder = $this->appData->getFolder('/');
			$file = $folder->getFile('backup-table-libresign_file_Version12000Date20250325143340.csv');
		} catch (NotFoundException) {
			return;
		}
		$handle = $file->read();
		fgetcsv($handle); // header
		while (($row = fgetcsv($handle)) !== false) {
			$update->setParameter('created_at', new \DateTime('@' . $row[1], new \DateTimeZone('UTC')), IQueryBuilder::PARAM_DATETIME_MUTABLE)
				->setParameter('id', $row[0]);
			$update->executeStatement();
		}
	}

	private function updateSignRequest(): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('libresign_sign_request')
			->set('created_at', $update->createParameter('created_at'))
			->set('signed', $update->createParameter('signed'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		try {
			$folder = $this->appData->getFolder('/');
			$file = $folder->getFile('backup-table-libresign_sign_request_Version12000Date20250325143340.csv');
		} catch (NotFoundException) {
			return;
		}
		$handle = $file->read();
		fgetcsv($handle); // header
		while (($row = fgetcsv($handle)) !== false) {
			$update->setParameter('created_at', new \DateTime('@' . $row[1], new \DateTimeZone('UTC')), IQueryBuilder::PARAM_DATETIME_MUTABLE)
				->setParameter('id', $row[0]);

			if ($row[2]) {
				$update->setParameter('signed', new \DateTime('@' . $row[2], new \DateTimeZone('UTC')), IQueryBuilder::PARAM_DATETIME_MUTABLE);
			} else {
				$update->setParameter('signed', null, IQueryBuilder::PARAM_NULL);
			}
			$update->executeStatement();
		}
	}
}
