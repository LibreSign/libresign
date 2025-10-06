<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use Doctrine\DBAL\Query\QueryBuilder;
use OCA\Libresign\Vendor\Pagerfanta\Adapter\AdapterInterface;
use OCA\Libresign\Vendor\Pagerfanta\Exception\InvalidArgumentException;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Adapter which calculates pagination from a Doctrine DBAL QueryBuilder.
 */
class PagerFantaQueryAdapter implements AdapterInterface {
	/**
	 * @throws InvalidArgumentException if a non-SELECT query is given
	 */
	public function __construct(
		private IQueryBuilder $queryBuilder,
		private IQueryBuilder $countQueryBuilder,
	) {
		if ($queryBuilder->getType() !== QueryBuilder::SELECT) {
			// @codeCoverageIgnoreStart
			throw new InvalidArgumentException('Only SELECT queries can be paginated.');
			// @codeCoverageIgnoreEnd
		}
	}

	#[\Override]
	public function getNbResults(): int {
		$total = $this->countQueryBuilder->executeQuery()->fetchOne();

		return abs((int)$total);
	}

	/**
	 * @psalm-suppress MixedReturnStatement
	 *
	 * @return array
	 */
	#[\Override]
	public function getSlice(int $offset, int $length): iterable {
		$qb = clone $this->queryBuilder;

		return $qb->setMaxResults($length)
			->setFirstResult($offset)
			->executeQuery()
			->fetchAll();
	}
}
