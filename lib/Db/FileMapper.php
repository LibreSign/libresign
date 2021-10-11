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
	/** @var File[] */
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
	public function getById(int $id): File {
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
	 */
	public function getByUuid(?string $uuid = null): \OCP\AppFramework\Db\Entity {
		if (!$uuid) {
			return array_values($this->file)[0];
		}
		if (empty($this->file[$uuid]) || ($this->file[$uuid]->getUuid() !== $uuid)) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('*')
				->from($this->getTableName())
				->where(
					$qb->expr()->eq('uuid', $qb->createNamedParameter($uuid, IQueryBuilder::PARAM_STR))
				);

			$this->file[$uuid] = $this->findEntity($qb);
		}
		return $this->file[$uuid];
	}

	/**
	 * Return LibreSign file by fileId
	 */
	public function getByFileId(?int $fileId = null): File {
		if (!$fileId) {
			return array_values($this->file)[0];
		}
		if (empty($this->file[$fileId]) || ($this->file[$fileId]->getNodeId() !== $fileId)) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('*')
				->from($this->getTableName())
				->where(
					$qb->expr()->orX(
						$qb->expr()->eq('node_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)),
						$qb->expr()->eq('signed_node_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
					)
				);

			$this->file[$fileId] = $this->findEntity($qb);
		}
		return $this->file[$fileId];
	}
}
