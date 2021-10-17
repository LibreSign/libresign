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

	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_file_element');
	}

	/**
	 * Undocumented function
	 *
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
}
