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
		private IQueryBuilder $countQueryBuilder,
	) {
		$adapter = new PagerFantaQueryAdapter($queryBuilder, $countQueryBuilder);
		parent::__construct($adapter);
	}

	/**
	 * @return static
	 */
	public function setRouteName(string $routeName = ''): self {
		$this->routeName = $routeName;
		return $this;
	}

	public function getPagination(int $page, int $length, array $filter = []): array {
		$this->setMaxPerPage($length);
		$total = $this->count();
		if ($total > $length) {
			return [
				'total' => $total,
				'current' => $this->linkToRoute(true, $page, $length, $filter),
				'next' => $this->linkToRoute($this->hasNextPage(), 'getNextPage', $length, $filter),
				'prev' => $this->linkToRoute($this->hasPreviousPage(), 'getPreviousPage', $length, $filter),
				'last' => $this->linkToRoute($this->hasNextPage(), 'getNbPages', $length, $filter),
				'first' => $this->linkToRoute($this->hasPreviousPage(), 1, $length, $filter),
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

	private function linkToRoute(bool $condition, int|string $page, int $length, array $filter): ?string {
		if (!$condition) {
			return null;
		}
		if (is_string($page)) {
			$page = $this->$page();
		}
		$url = $this->urlGenerator->linkToRouteAbsolute(
			$this->routeName,
			array_merge(['page' => $page, 'length' => $length, 'apiVersion' => 'v1'], $filter)
		);
		$url = $this->sortParameters($url);
		return $url;
	}

	/**
	 * This is necessary to fix problem at integration tests because the method linkToRoute change the order of parameters
	 */
	private function sortParameters(?string $url): ?string {
		if (!$url) {
			return $url;
		}
		parse_str(parse_url($url, PHP_URL_QUERY), $query);
		ksort($query);
		$url = strtok($url, '?') . '?' . http_build_query($query);
		return $url;
	}
}
