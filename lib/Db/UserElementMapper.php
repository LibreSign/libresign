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
 * @method UserElement insert(UserElement $entity)
 * @method UserElement update(UserElement $entity)
 * @method UserElement insertOrUpdate(UserElement $entity)
 * @method UserElement delete(UserElement $entity)
 */
class UserElementMapper extends QBMapper {
	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_user_element');
	}

	/**
	 * @return UserElement[]
	 */
	public function getByUserId($userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('ue.*')
			->from($this->getTableName(), 'ue')
			->where(
				$qb->expr()->eq('ue.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntities($qb);
	}
}
