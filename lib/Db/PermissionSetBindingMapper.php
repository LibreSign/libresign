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
 * @template-extends CachedQBMapper<PermissionSetBinding>
 */
class PermissionSetBindingMapper extends CachedQBMapper {
	public function __construct(IDBConnection $db, ICacheFactory $cacheFactory) {
		parent::__construct($db, $cacheFactory, 'libresign_permission_set_binding');
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getById(int $id): PermissionSetBinding {
		$cached = $this->cacheGet('id:' . $id);
		if ($cached instanceof PermissionSetBinding) {
			return $cached;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		/** @var PermissionSetBinding */
		$entity = $this->findEntity($qb);
		$this->cacheEntity($entity);
		return $entity;
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getByTarget(string $targetType, string $targetId): PermissionSetBinding {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('target_type', $qb->createNamedParameter($targetType)))
			->andWhere($qb->expr()->eq('target_id', $qb->createNamedParameter($targetId)));

		/** @var PermissionSetBinding */
		$entity = $this->findEntity($qb);
		$this->cacheEntity($entity);
		return $entity;
	}

	/**
	 * @param list<string> $targetIds
	 * @return list<PermissionSetBinding>
	 */
	public function findByTargets(string $targetType, array $targetIds): array {
		if ($targetIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('target_type', $qb->createNamedParameter($targetType)))
			->andWhere($qb->expr()->in('target_id', $qb->createNamedParameter($targetIds, IQueryBuilder::PARAM_STR_ARRAY)));

		/** @var list<PermissionSetBinding> */
		$entities = $this->findEntities($qb);
		foreach ($entities as $entity) {
			$this->cacheEntity($entity);
		}

		return $entities;
	}

	/**
	 * @return list<PermissionSetBinding>
	 */
	public function findByTargetType(string $targetType): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('target_type', $qb->createNamedParameter($targetType)));

		/** @var list<PermissionSetBinding> */
		$entities = $this->findEntities($qb);
		foreach ($entities as $entity) {
			$this->cacheEntity($entity);
		}

		return $entities;
	}
}
