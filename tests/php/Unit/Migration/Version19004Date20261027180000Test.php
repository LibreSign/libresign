<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use OC\Migration\NullOutput;
use OCA\Libresign\Migration\Version19004Date20261027180000;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

final class Version19004Date20261027180000Test extends TestCase {
	public function testRetriesLostCompareAndSwapWhenAnotherWriterRepairsTheType(): void {
		$old = ['configvalue' => '{"enabled":true}', 'type' => IAppConfig::VALUE_BOOL, 'lazy' => 0];
		$repaired = ['configvalue' => '{"enabled":true}', 'type' => IAppConfig::VALUE_STRING, 'lazy' => 0];
		$migration = $this->migrationWithRows(
			[$old, $repaired, false, false, $repaired, false, false],
			1,
		);

		$migration->postSchemaChange(new NullOutput(), static fn () => null, []);
	}

	public function testDoesNotMarkPersistentConcurrentChangesAsSuccessful(): void {
		$old = ['configvalue' => '{"enabled":true}', 'type' => IAppConfig::VALUE_BOOL, 'lazy' => 0];
		$migration = $this->migrationWithRows([$old, $old, $old, $old], 3);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Concurrent AppConfig change prevented metadata repair for add_footer');

		$migration->postSchemaChange(new NullOutput(), static fn () => null, []);
	}

	/**
	 * @param list<array<string, int|string>|false> $rows
	 */
	private function migrationWithRows(array $rows, int $updateAttempts): Version19004Date20261027180000 {
		$result = $this->createMock(IResult::class);
		$result->expects(self::exactly(count($rows)))->method('fetchAssociative')
			->willReturnOnConsecutiveCalls(...$rows);
		$result->expects(self::exactly(count($rows)))->method('closeCursor');

		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('field = :parameter');

		$qb = $this->createMock(IQueryBuilder::class);
		foreach (['select', 'from', 'where', 'andWhere', 'update', 'set'] as $method) {
			$qb->method($method)->willReturnSelf();
		}
		$qb->method('expr')->willReturn($expr);
		$qb->method('createNamedParameter')->willReturn(':parameter');
		$qb->method('executeQuery')->willReturn($result);
		$qb->expects(self::exactly($updateAttempts))->method('executeStatement')->willReturn(0);

		$connection = $this->createMock(IDBConnection::class);
		$connection->method('getQueryBuilder')->willReturn($qb);

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects(self::once())->method('clearCache');

		return new Version19004Date20261027180000($connection, $appConfig);
	}
}
