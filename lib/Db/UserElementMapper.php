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
	/** @var UserElement[] */
	private $cache = [];

	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_user_element');
	}

	private function getQueryBuilder(array $data): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb->select('ue.*')
			->from($this->getTableName(), 'ue');

		if (isset($data['id'])) {
			$qb->andWhere(
				$qb->expr()->eq('ue.id', $qb->createNamedParameter($data['id'], IQueryBuilder::PARAM_INT))
			);
		}
		if (isset($data['file_id'])) {
			$qb->andWhere(
				$qb->expr()->eq('ue.file_id', $qb->createNamedParameter($data['file_id'], IQueryBuilder::PARAM_INT))
			);
		}
		if (isset($data['type'])) {
			$qb->andWhere(
				$qb->expr()->eq('ue.type', $qb->createNamedParameter($data['type']))
			);
		}
		if (isset($data['user_id'])) {
			$qb->andWhere(
				$qb->expr()->eq('ue.user_id', $qb->createNamedParameter($data['user_id']))
			);
		}
		return $qb;
	}

	public function findOne(array $data): UserElement {
		$qb = $this->getQueryBuilder($data);
		try {
			$row = $this->findOneQuery($qb);
		} catch (\Throwable $th) {
			$qb->andWhere(
				$qb->expr()->eq('ue.starred', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			);
			$row = $this->findOneQuery($qb);
		}
		$userElement = $this->mapRowToEntity($row);
		return $userElement;
	}

	/**
	 * @return UserElement[]
	 */
	public function findMany(array $data): array {
		$qb = $this->getQueryBuilder($data);
		return $this->findEntities($qb);
	}
}
