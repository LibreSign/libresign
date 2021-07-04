<?php

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

	public function setRootPath(string $rootPath = '') {
		$this->rootPath = $rootPath;
		return $this;
	}

	public function getPagination($page, $length) {
		$pagination['total'] = $this->count();
		if ($pagination['total'] > $length) {
			$pagination['current'] = '/file/list?page=' . $page . '&length=' . $length;
			$pagination['next'] = '/file/list?page=' . $page . '&length=' . $length;
			$pagination['prev'] = '/file/list' .
				'?page=' . ($this->hasPreviousPage() ? $this->getPreviousPage() : 1) .
				'&length=' . $length;
			$pagination['last'] = '/file/list' .
				'?page=' . ($this->hasNextPage() ? $this->getNextPage() : 1) .
				'&length=' . $length;
			$pagination['first'] = '/file/list?page=1&length=' . $length;
		} else {
			$pagination['current'] = '';
			$pagination['next'] = '';
			$pagination['prev'] = '';
			$pagination['last'] = '';
			$pagination['first'] = '';
		}
		return $pagination;
	}
}
