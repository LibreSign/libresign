<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\ICacheFactory;
use OCP\IDBConnection;

/**
 * @template-extends CachedQBMapper<PermissionSet>
 */
class PermissionSetMapper extends CachedQBMapper {
	public function __construct(IDBConnection $db, ICacheFactory $cacheFactory) {
		parent::__construct($db, $cacheFactory, 'libresign_permission_set');
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getById(int $id): PermissionSet {
		$cached = $this->cacheGet('id:' . $id);
		if ($cached instanceof PermissionSet) {
			return $cached;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		/** @var PermissionSet */
		$entity = $this->findEntity($qb);
		$this->cacheEntity($entity);
		return $entity;
	}

	/**
	 * @param list<int> $ids
	 * @return list<PermissionSet>
	 */
	public function findByIds(array $ids): array {
		if ($ids === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));

		/** @var list<PermissionSet> */
		$entities = $this->findEntities($qb);
		foreach ($entities as $entity) {
			$this->cacheEntity($entity);
		}

		return $entities;
	}
}
