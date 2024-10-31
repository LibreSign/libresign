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

namespace OCA\Libresign\Helper;

use OCA\Libresign\Db\PagerFantaQueryAdapter;
use OCP\IURLGenerator;
use OCP\DB\QueryBuilder\IQueryBuilder;
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
		$pagination['total'] = $this->count();
		if ($pagination['total'] > $length) {
			$pagination['current'] = $this->linkToRoute($page, $length, $filter);
			$pagination['next'] = $this->hasNextPage()
				? $this->linkToRoute($this->getNextPage(), $length, $filter)
				: null;
			$pagination['prev'] = $this->hasPreviousPage()
				? $this->linkToRoute($this->getPreviousPage(), $length, $filter)
				: null;
			$pagination['last'] = $this->hasNextPage()
				? $this->linkToRoute($this->getNbPages(), $length, $filter)
				: null;
			$pagination['first'] = $this->hasPreviousPage()
				? $this->linkToRoute(1, $length, $filter)
				: null;
		} else {
			$pagination['current'] = null;
			$pagination['next'] = null;
			$pagination['prev'] = null;
			$pagination['last'] = null;
			$pagination['first'] = null;
		}
		return $pagination;
	}

	private function linkToRoute(int $page, int $length, array $filter): string {
		return $this->urlGenerator->linkToRoute(
			$this->routeName,
			array_merge(['page' => $page, 'length' => $length, 'apiVersion' => 'v1'], $filter)
		);
	}
}
