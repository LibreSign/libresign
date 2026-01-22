<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;

/**
 * @template T of Entity
 * @template-extends QBMapper<T>
 */
abstract class CachedQBMapper extends QBMapper {
	protected const DEFAULT_CACHE_TTL = 30;

	private ?ICache $cache = null;
	private int $cacheTtl = self::DEFAULT_CACHE_TTL;

	public function __construct(
		IDBConnection $db,
		ICacheFactory $cacheFactory,
		string $tableName,
		int $cacheTtl = self::DEFAULT_CACHE_TTL,
	) {
		parent::__construct($db, $tableName);
		$this->cacheTtl = $cacheTtl;
		if ($cacheFactory->isAvailable()) {
			$this->cache = $cacheFactory->createDistributed($tableName);
		}
	}

	#[\Override]
	public function insert(Entity $entity): Entity {
		$inserted = parent::insert($entity);
		$this->cacheEntity($inserted);
		return $inserted;
	}

	#[\Override]
	public function update(Entity $entity): Entity {
		$updated = parent::update($entity);
		$this->cacheEntity($updated);
		return $updated;
	}

	#[\Override]
	public function delete(Entity $entity): Entity {
		$deleted = parent::delete($entity);
		$this->evictEntity($deleted);
		return $deleted;
	}

	private function cachePrefix(): string {
		return $this->getTableName();
	}

	private function cacheKey(string $key): string {
		return $this->cachePrefix() . ':' . $key;
	}

	protected function cacheGet(string $key): mixed {
		return $this->cache?->get($this->cacheKey($key));
	}

	protected function cacheSet(string $key, mixed $value): void {
		if ($this->cache) {
			$this->cache->set($this->cacheKey($key), $value, $this->cacheTtl);
		}
	}

	protected function cacheRemove(string $key): void {
		if ($this->cache) {
			$this->cache->remove($this->cacheKey($key));
		}
	}

	private function cacheClear(): void {
		if ($this->cache) {
			$this->cache->clear($this->cachePrefix() . ':');
		}
	}

	/**
	 * @return list<string>
	 */
	protected function getEntityCacheKeys(Entity $entity): array {
		$id = $entity->getId();
		if ($id === null) {
			return [];
		}
		return ['id:' . $id];
	}

	protected function cacheEntity(Entity $entity): void {
		foreach ($this->getEntityCacheKeys($entity) as $key) {
			$this->cacheSet($key, $entity);
		}
	}

	private function evictEntity(Entity $entity): void {
		foreach ($this->getEntityCacheKeys($entity) as $key) {
			$this->cacheRemove($key);
		}
	}
}
