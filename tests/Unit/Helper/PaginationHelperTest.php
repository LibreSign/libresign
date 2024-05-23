<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Helper;

use OC\DB\ResultAdapter;
use OCA\Libresign\Helper\Pagination;
use OCP\DB\QueryBuilder\IQueryBuilder;

class PaginationHelperTest extends \OCA\Libresign\Tests\Unit\TestCase {
	public function testWithOnePage():void {
		$queryBuilder = $this->createMock(IQueryBuilder::class);
		$queryBuilder
			->method('getType')
			->willReturn(0);

		$result = $this->createMock(ResultAdapter::class);
		$result
			->method('fetch')
			->willReturn(['total_results' => 1]);
		$queryBuilder
			->method('executeQuery')
			->willReturn($result);
		$pagination = new Pagination(
			$queryBuilder,
			function ():void {
			}
		);
		$actual = $pagination->getPagination(1, 10);
		$expected = [
			'total' => 1,
			'current' => '',
			'next' => '',
			'prev' => '',
			'last' => '',
			'first' => ''
		];
		$this->assertEquals($expected, $actual);
	}

	public function testWithTwoPages():void {
		$queryBuilder = $this->createMock(IQueryBuilder::class);
		$queryBuilder
			->method('getType')
			->willReturn(0);

		$result = $this->createMock(ResultAdapter::class);
		$result
			->method('fetch')
			->willReturn(['total_results' => 2]);
		$queryBuilder
			->method('executeQuery')
			->willReturn($result);
		$pagination = new Pagination(
			$queryBuilder,
			function ():void {
			}
		);
		$pagination->setRootPath('/root/list');
		$actual = $pagination->getPagination(1, 1);
		$expected = [
			'total' => 2,
			'current' => '/root/list?page=1&length=1',
			'next' => '/root/list?page=2&length=1',
			'prev' => null,
			'last' => '/root/list?page=2&length=1',
			'first' => null
		];
		$this->assertEquals($expected, $actual);
	}
}
