<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use Doctrine\DBAL\Query\QueryBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Exception\InvalidArgumentException;
use ReflectionClass;

/**
 * Adapter which calculates pagination from a Doctrine DBAL QueryBuilder.
 */
class PagerFantaQueryAdapter implements AdapterInterface {
	/**
	 * @throws InvalidArgumentException if a non-SELECT query is given
	 */
	public function __construct(
		private IQueryBuilder $queryBuilder,
	) {
		if ($queryBuilder->getType() !== QueryBuilder::SELECT) {
			// @codeCoverageIgnoreStart
			throw new InvalidArgumentException('Only SELECT queries can be paginated.');
			// @codeCoverageIgnoreEnd
		}
	}

	public function getNbResults(): int {
		/**
		 * The clone isn't working fine if we clone the property $this->queryBuilder
		 * because the internal property "queryBuilder" of $this->queryBuilder is
		 * a reference and the clone don't work with reference. To solve this
		 * was used reflection.
		 */
		$reflect = new ReflectionClass($this->queryBuilder);
		$reflectionProperty = $reflect->getProperty('queryBuilder');
		$reflectionProperty->setAccessible(true);
		$qb = $reflectionProperty->getValue($this->queryBuilder);
		$originalQueryBuilder = clone $qb;

		$this->queryBuilder->resetQueryPart('select')
			->resetQueryPart('groupBy')
			->select($this->queryBuilder->func()->count())
			->setFirstResult(0)
			->setMaxResults(null);
		$total = $this->queryBuilder->executeQuery()->fetchOne();

		$reflectionProperty->setValue($this->queryBuilder, $originalQueryBuilder);

		return abs((int)$total);
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 *
	 * @return array
	 */
	public function getSlice(int $offset, int $length): iterable {
		$qb = clone $this->queryBuilder;

		return $qb->setMaxResults($length)
			->setFirstResult($offset)
			->executeQuery()
			->fetchAll();
	}
}
