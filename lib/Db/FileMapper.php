<?php

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\DB
 *
 * @codeCoverageIgnore
 * @method File insert(File $entity)
 * @method File update(File $entity)
 * @method File insertOrUpdate(File $entity)
 * @method File delete(File $entity)
 */
class FileMapper extends QBMapper {
	/** @var File */
	private $file;

	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_file');
	}

	/**
	 * Return LibreSign file by ID
	 *
	 * @return File Row of table libresign_file
	 */
	public function getById($id) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * Return LibreSign file by UUID
	 *
	 * @return Entity Row of table libresign_file
	 */
	public function getByUuid(?string $uuid = null) {
		if (!$this->file || ($uuid && $this->file->getUuid() !== $uuid)) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('*')
				->from($this->getTableName())
				->where(
					$qb->expr()->eq('uuid', $qb->createNamedParameter($uuid, IQueryBuilder::PARAM_STR))
				);

			$this->file = $this->findEntity($qb);
		}
		return $this->file;
	}

	/**
	 * Return LibreSign file by fileId
	 *
	 * @return Entity Row of table libresign_file
	 */
	public function getByFileId(?int $fileId = null) {
		if (!$this->file || ($fileId && $this->file->getNodeId() !== $fileId)) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('*')
				->from($this->getTableName())
				->where(
					$qb->expr()->eq('node_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
				);

			$this->file = $this->findEntity($qb);
		}
		return $this->file;
	}
}
