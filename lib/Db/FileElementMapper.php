<?php

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Class FileElementsMapper
 *
 * @package OCA\Libresign\DB
 *
 * @codeCoverageIgnore
 * @method FileUser insert(FileUser $entity)
 * @method FileUser update(FileUser $entity)
 * @method FileUser insertOrUpdate(FileUser $entity)
 * @method FileUser delete(FileUser $entity)
 */
class FileElementMapper extends QBMapper {
	/** @var FileElement[][] */
	private $cache = [];

	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_file_element');
	}

	/**
	 * @param integer $fileId
	 * @return FileElement[]
	 */
	public function getByFileId(int $fileId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('fe.*')
			->from($this->getTableName(), 'fe')
			->where(
				$qb->expr()->eq('fe.file_id', $qb->createNamedParameter($fileId))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param integer $fileId
	 * @param string $userId
	 * @return FileElement[]
	 */
	public function getByFileIdAndUserId(int $fileId, string $userId): array {
		if (!isset($this->cache['fileId'][$fileId][$userId])) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('fe.*')
				->from($this->getTableName(), 'fe')
				->join('fe', 'libresign_file_user', 'fu', 'fu.id = fe.file_user_id')
				->where(
					$qb->expr()->eq('fe.file_id', $qb->createNamedParameter($fileId))
				)
				->andWhere(
					$qb->expr()->eq('fu.user_id', $qb->createNamedParameter($userId))
				);

			$this->cache['fileId'][$fileId][$userId] = $this->findEntities($qb);
		}
		return $this->cache['fileId'][$fileId][$userId];
	}

	public function getByDocumentElementIdAndFileUserId(int $documentElementId, string $userId): FileElement {
		if (!isset($this->cache['documentElementId'][$documentElementId])) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('fe.*')
				->from($this->getTableName(), 'fe')
				->join('fe', 'libresign_file_user', 'fu', 'fu.id = fe.file_user_id')
				->where(
					$qb->expr()->eq('fe.id', $qb->createNamedParameter($documentElementId, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
					$qb->expr()->eq('fu.user_id', $qb->createNamedParameter($userId))
				);

			$this->cache['documentElementId'][$documentElementId] = $this->findEntity($qb);
		}
		return $this->cache['documentElementId'][$documentElementId];
	}

	public function getById(int $id): FileElement {
		if (!isset($this->cache['documentElementId'][$id])) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('fe.*')
				->from($this->getTableName(), 'fe')
				->where(
					$qb->expr()->eq('fe.id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
				);

			$this->cache['documentElementId'][$id] = $this->findEntity($qb);
		}
		return $this->cache['documentElementId'][$id];
	}
}
