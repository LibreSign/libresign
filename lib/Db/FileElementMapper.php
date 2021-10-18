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
	private $signers = [];

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
				$qb->expr()->eq('fe.file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}

	public function getByFileIdAndFileUserId(int $fileId, int $userId) {
		if (!isset($this->signers['fileId'][$fileId][$userId])) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('fe.*')
				->from($this->getTableName(), 'fe')
				->where(
					$qb->expr()->eq('fe.signature_file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('fe.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				);

			$this->signers['fileId'][$fileId][$userId] = $this->findEntity($qb);
		}
		return $this->signers['fileId'][$fileId][$userId];
	}
}
