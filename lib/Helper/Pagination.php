<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Helper;

use OCA\Libresign\Db\PagerFantaQueryAdapter;
use OCP\DB\QueryBuilder\IQueryBuilder;
use Pagerfanta\Pagerfanta;

class Pagination extends Pagerfanta {
	/** @var string */
	private $rootPath;
	public function __construct(
		IQueryBuilder $queryBuilder,
		callable $countQueryBuilderModifier
	) {
		$adapter = new PagerFantaQueryAdapter($queryBuilder, $countQueryBuilderModifier);
		parent::__construct($adapter);
	}

	/**
	 * @return static
	 */
	public function setRootPath(string $rootPath = ''): self {
		$this->rootPath = $rootPath;
		return $this;
	}

	public function getPagination(?int $page, ?int $length): array {
		$this->setMaxPerPage($length);
		$pagination['total'] = $this->count();
		if ($pagination['total'] > $length) {
			$pagination['current'] = $this->rootPath . '?page=' . $page . '&length=' . $length;
			$pagination['next'] = $this->hasNextPage()      ? $this->rootPath . '?page=' . $this->getNextPage() . '&length=' . $length : null;
			$pagination['prev'] = $this->hasPreviousPage()  ? $this->rootPath . '?page=' . $this->getPreviousPage() . '&length=' . $length : null;
			$pagination['last'] = $this->hasNextPage()      ? $this->rootPath . '?page=' . $this->getNbPages() . '&length=' . $length : null;
			$pagination['first'] = $this->hasPreviousPage() ? $this->rootPath . '?page=1&length=' . $length : null;
		} else {
			$pagination['current'] = null;
			$pagination['next'] = null;
			$pagination['prev'] = null;
			$pagination['last'] = null;
			$pagination['first'] = null;
		}
		return $pagination;
	}
}
