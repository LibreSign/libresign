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
class AccountFileMapper extends QBMapper {
	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_account_file');
	}

	/**
	 * @return Entity Row of table libresign_account_file
	 */
	public function getByUserAndType(string $userId, string $type) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('file_type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}
}
