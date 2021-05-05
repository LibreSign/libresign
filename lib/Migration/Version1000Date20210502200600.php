<?php

declare(strict_types=1);

namespace OCA\Libresign\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20210502200600 extends SimpleMigrationStep {

	/** @var IDBConnection */
	protected $connection;
	private $rows = [];

	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		$query = $this->connection->getQueryBuilder();
		$query->select('id', 'file_id')
			->from('libresign_file_user', 'fu');
		$this->rows = $query->execute()->fetchAll();
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('libresign_file_user');

		$table->dropColumn('file_id');

		$table->addColumn('file_id2', Types::BIGINT, [
			'notnull' => false,
			'length' => 11,
			'unsigned' => true,
		]);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		foreach ($this->rows as $row) {
			$query = $this->connection->getQueryBuilder();
			$query
				->update('libresign_file_user')
				->set('file_id2', $query->createNamedParameter((int)$row['file_id']))
				->where($query->expr()->eq('id', $query->createNamedParameter($row['id'])));

			$query->execute();
		}
	}
}
