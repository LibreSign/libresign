<?php

declare(strict_types=1);

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\Types;
use OCP\IDBConnection;

class ProductMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'gopaperless_products', Product::class);
	}

	/**
	 * CRITICAL:
	 * Resolve the active/default product for a given code.
	 *
	 * This is what PaymentService will use.
	 */
	public function findDefaultByCode(string $code): ?Product {

		$qb = $this->db->getQueryBuilder();

		$qb->select('p.*')
			->from($this->getTableName(), 'p')
			->where(
				$qb->expr()->andX(
					$qb->expr()->eq(
						'p.code',
						$qb->createNamedParameter($code, Types::STRING)
					),
					$qb->expr()->eq(
						'p.is_default',
						$qb->createNamedParameter(true, Types::BOOLEAN)
					),
					$qb->expr()->eq(
						'p.active',
						$qb->createNamedParameter(true, Types::BOOLEAN)
					)
				)
			)
			->setMaxResults(1);

		try {
			/** @var Product $entity */
			$entity = $this->findEntity($qb);
			return $entity;
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	/**
	 * Fetch all products for a given code (admin UI usage)
	 * @throws Exception
	 */
	public function findByCode(string $code): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('p.*')
			->from($this->getTableName(), 'p')
			->where(
				$qb->expr()->eq(
					'p.code',
					$qb->createNamedParameter($code, Types::STRING)
				)
			)
			->orderBy('p.created_at', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * Fetch all products (admin UI usage)
	 * @throws Exception
	 */
	public function findAll(): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('p.*')
			->from($this->getTableName(), 'p')
			->orderBy('p.created_at', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * Find product by ID
	 */
	public function findById(int $id): ?Product {

		$qb = $this->db->getQueryBuilder();

		$qb->select('p.*')
			->from($this->getTableName(), 'p')
			->where(
				$qb->expr()->eq(
					'p.id',
					$qb->createNamedParameter($id, Types::INTEGER)
				)
			)
			->setMaxResults(1);

		try {
			/** @var Product $entity */
			$entity = $this->findEntity($qb);
			return $entity;
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}
}
