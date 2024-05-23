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

/**
 * Adapter which calculates pagination from a Doctrine DBAL QueryBuilder.
 */
class PagerFantaQueryAdapter implements AdapterInterface {
	private IQueryBuilder $queryBuilder;

	/**
	 * @var callable
	 * @phpstan-var callable(QueryBuilder): void
	 */
	private $countQueryBuilderModifier;

	/**
	 * @phpstan-param callable(QueryBuilder): void $countQueryBuilderModifier
	 *
	 * @throws InvalidArgumentException if a non-SELECT query is given
	 */
	public function __construct(IQueryBuilder $queryBuilder, callable $countQueryBuilderModifier) {
		$queryBuilder->getType();
		if (QueryBuilder::SELECT !== $queryBuilder->getType()) {
			// @codeCoverageIgnoreStart
			throw new InvalidArgumentException('Only SELECT queries can be paginated.');
			// @codeCoverageIgnoreEnd
		}

		$this->queryBuilder = clone $queryBuilder;
		$this->countQueryBuilderModifier = $countQueryBuilderModifier;
	}

	public function getNbResults(): int {
		$qb = $this->prepareCountQueryBuilder();
		$result = $qb->executeQuery()->fetch();
		$values = array_values($result);
		/** @var int<0, max> */
		return (int) $values[0];
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

	/**
	 * @psalm-suppress MixedReturnStatement
	 */
	private function prepareCountQueryBuilder(): IQueryBuilder {
		$qb = clone $this->queryBuilder;
		$callable = $this->countQueryBuilderModifier;

		$callable($qb);

		return $qb;
	}
}
