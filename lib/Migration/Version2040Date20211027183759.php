<?php

declare(strict_types=1);

namespace OCA\Libresign\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCA\Libresign\Handler\TCPDILibresign;
use OCP\DB\ISchemaWrapper;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version2040Date20211027183759 extends SimpleMigrationStep {
	/** @var IRootFolder*/
	private $root;
	public function __construct(IRootFolder $root, IDBConnection $connection) {
		$this->connection = $connection;
		$this->root = $root;
	}

	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$query = $this->connection->getQueryBuilder();
		$query->select('id', 'node_id', 'user_id')
			->from('libresign_file', 'f');
		$this->rows = $query->execute()->fetchAll();
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
			$file = $userFolder->getById($row['node_id']);
			if (count($file) >= 1) {
				$pdf = new TCPDILibresign();
				$pdf->setSourceData($file[0]->getContent());
				$data = $pdf->getPagesMetadata();
				$data['extension'] = 'pdf';
				$json = json_encode($data);
				$query = $this->connection->getQueryBuilder();
				$query
					->update('libresign_file')
					->set('metadata', $query->createNamedParameter($json))
					->where($query->expr()->eq('id', $query->createNamedParameter($row['id'])));
		
				$query->execute();
			}
		}
	}
}
