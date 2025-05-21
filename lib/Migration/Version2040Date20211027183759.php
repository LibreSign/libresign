<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCA\Libresign\Service\PdfParserService;
use OCP\DB\ISchemaWrapper;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2040Date20211027183759 extends SimpleMigrationStep {
	/** @var array */
	private $rows;
	public function __construct(
		private IDBConnection $connection,
		private IRootFolder $root,
		private PdfParserService $pdfParserService,
	) {
	}

	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->select('id', 'node_id', 'user_id')
			->from('libresign_file', 'f');
		$this->rows = $query->executeQuery()->fetchAll();
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('libresign_file');

		if (!$table->hasColumn('metadata')) {
			$table->addColumn('metadata', Types::TEXT, [
				'notnull' => false
			]);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		foreach ($this->rows as $row) {
			$userFolder = $this->root->getUserFolder($row['user_id']);
			/** @var File[] */
			$file = $userFolder->getById($row['node_id']);
			if (count($file) >= 1) {
				$data = $this->pdfParserService
					->setFile($file[0])
					->getPageDimensions();
				$json = json_encode($data);
				$query = $this->connection->getQueryBuilder();
				$query
					->update('libresign_file')
					->set('metadata', $query->createNamedParameter($json))
					->where($query->expr()->eq('id', $query->createNamedParameter($row['id'])));

				$query->executeStatement();
			}
		}
	}
}
