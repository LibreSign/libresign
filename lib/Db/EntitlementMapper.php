<?php

declare(strict_types=1);

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\Types;
use OCP\IDBConnection;

class EntitlementMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'gopaperless_entitlements', Entitlement::class);
	}

	/**
	 * Find entitlement by ID
	 * @throws Exception
	 */
	public function findById(int $id): ?Entitlement {

		$qb = $this->db->getQueryBuilder();

		$qb->select('e.*')
			->from($this->getTableName(), 'e')
			->where(
				$qb->expr()->eq(
					'e.id',
					$qb->createNamedParameter($id, Types::INTEGER)
				)
			)
			->setMaxResults(1);

		try {
			/** @var Entitlement $entity */
			$entity = $this->findEntity($qb);
			return $entity;
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	/**
	 * Fetch all entitlements for a user + product
	 *
	 * Used by service to determine usable entitlements
	 * @throws Exception
	 */
	public function findByUserAndProduct(string $userId, string $productCode): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('e.*')
			->from($this->getTableName(), 'e')
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq(
						'e.user_id',
						$qb->createNamedParameter($userId, Types::STRING)
					),
					$qb->expr()->eq(
						'e.product_code',
						$qb->createNamedParameter($productCode, Types::STRING)
					)
				)
			)
			->orderBy('e.created_at', 'ASC'); // oldest first (FIFO consumption)

		return $this->findEntities($qb);
	}

	/**
	 * 🔥 Optional optimization (can skip for now):
	 * Fetch only potentially usable entitlements
	 *
	 * NOTE:
	 * - Does NOT handle expiry logic fully (handled in entity/service)
	 * - Helps reduce filtering in PHP
	 * @throws Exception
	 */
	public function findPotentiallyUsable(string $userId, string $productCode): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('e.*')
			->from($this->getTableName(), 'e')
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq(
						'e.user_id',
						$qb->createNamedParameter($userId, Types::STRING)
					),
					$qb->expr()->eq(
						'e.product_code',
						$qb->createNamedParameter($productCode, Types::STRING)
					),
					// remaining_uses > 0 OR NULL (unlimited)
					$qb->expr()->orX(
						$qb->expr()->isNull('e.remaining_uses'),
						$qb->expr()->gt(
							'e.remaining_uses',
							$qb->createNamedParameter(0, Types::INTEGER)
						)
					)
				)
			)
			->orderBy('e.created_at', 'ASC');

		return $this->findEntities($qb);
	}
}
