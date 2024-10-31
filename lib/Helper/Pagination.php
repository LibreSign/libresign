<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Helper;

use OCA\Libresign\Db\PagerFantaQueryAdapter;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IURLGenerator;
use Pagerfanta\Pagerfanta;

class Pagination extends Pagerfanta {
	private string $routeName;
	public function __construct(
		IQueryBuilder $queryBuilder,
		private IURLGenerator $urlGenerator,
	) {
		$adapter = new PagerFantaQueryAdapter($queryBuilder);
		parent::__construct($adapter);
	}

	/**
	 * @return static
	 */
	public function setRouteName(string $routeName = ''): self {
		$this->routeName = $routeName;
		return $this;
	}

	public function getPagination(?int $page, ?int $length, array $filter = []): array {
		$this->setMaxPerPage($length);
		$total = $this->count();
		if ($total > $length) {
			return [
				'total' => $total,
				'current' => $this->linkToRoute($page, $length, $filter),
				'next' => $this->hasNextPage()
					? $this->linkToRoute($this->getNextPage(), $length, $filter)
					: null,
				'prev' => $this->hasPreviousPage()
					? $this->linkToRoute($this->getPreviousPage(), $length, $filter)
					: null,
				'last' => $this->hasNextPage()
					? $this->linkToRoute($this->getNbPages(), $length, $filter)
					: null,
				'first' => $this->hasPreviousPage()
					? $this->linkToRoute(1, $length, $filter)
					: null,
			];
		}
		return [
			'total' => $total,
			'current' => null,
			'next' => null,
			'prev' => null,
			'last' => null,
			'first' => null,
		];
	}

	private function linkToRoute(int $page, int $length, array $filter): string {
		return $this->urlGenerator->linkToRouteAbsolute(
			$this->routeName,
			array_merge(['page' => $page, 'length' => $length, 'apiVersion' => 'v1'], $filter)
		);
	}
}
