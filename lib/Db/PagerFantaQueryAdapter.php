<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
	private IQueryBuilder $queryBuilder;

	private $countQueryBuilderModifier;

	/**
	 * @phpstan-param callable(QueryBuilder): void $countQueryBuilderModifier
	 *
	 * @throws InvalidArgumentException if a non-SELECT query is given
	 */
	public function __construct(IQueryBuilder $queryBuilder, callable $countQueryBuilderModifier) {
		if (QueryBuilder::SELECT !== $queryBuilder->getType()) {
			// @codeCoverageIgnoreStart
			throw new InvalidArgumentException('Only SELECT queries can be paginated.');
			// @codeCoverageIgnoreEnd
		}

		$this->queryBuilder = clone $queryBuilder;
		$this->countQueryBuilderModifier = $countQueryBuilderModifier;
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

		$callable = $this->countQueryBuilderModifier;
		$total = $callable($this->queryBuilder);

		$reflectionProperty->setValue($this->queryBuilder, $originalQueryBuilder);

		return $total;
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
