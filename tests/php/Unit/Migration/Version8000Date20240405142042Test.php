<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use OCA\Libresign\Migration\Version8000Date20240405142042;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\ColumnType;
use OCP\DB\Schema\IColumn;
use OCP\DB\Schema\ITable;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class Version8000Date20240405142042Test extends TestCase {
	private IDBConnection&MockObject $connection;
	private IOutput&MockObject $output;

	#[\Override]
	protected function setUp(): void {
		parent::setUp();
		$this->connection = $this->createMock(IDBConnection::class);
		$this->output = $this->createMock(IOutput::class);
	}

	private function mockTable(bool $hasMetadataColumn, ?ColumnType $currentType): ITable&MockObject {
		$table = $this->createMock(ITable::class);
		$table->method('hasColumn')->with('metadata')->willReturn($hasMetadataColumn);

		if ($hasMetadataColumn) {
			$column = $this->createMock(IColumn::class);
			$column->method('getType')->willReturn($currentType);
			$table->method('getColumn')->with('metadata')->willReturn($column);
		}

		return $table;
	}

	private function mockSchema(object $platform, array $tables): ISchemaWrapper&MockObject {
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('getDatabasePlatform')->willReturn($platform);
		$schema->method('getTable')->willReturnCallback(static fn (string $name) => $tables[$name]);
		return $schema;
	}

	public function testChangeSchemaConvertsLegacyColumnsToJsonOnNonPostgresPlatform(): void {
		$fileTable = $this->mockTable(true, ColumnType::Text);
		$fileTable->expects($this->once())
			->method('modifyColumn')
			->with('metadata', ['type' => 'json']);

		$elementTable = $this->mockTable(true, ColumnType::Text);
		$elementTable->expects($this->once())
			->method('modifyColumn')
			->with('metadata', ['type' => 'json']);

		$schema = $this->mockSchema(new SqlitePlatform(), [
			'libresign_file' => $fileTable,
			'libresign_file_element' => $elementTable,
		]);

		$migration = new Version8000Date20240405142042($this->connection);
		$result = $migration->changeSchema($this->output, static fn () => $schema, []);

		self::assertSame($schema, $result);
	}

	public function testChangeSchemaIsNoopWhenColumnsAreAlreadyJson(): void {
		$fileTable = $this->mockTable(true, ColumnType::Json);
		$fileTable->expects($this->never())->method('modifyColumn');

		$elementTable = $this->mockTable(true, ColumnType::Json);
		$elementTable->expects($this->never())->method('modifyColumn');

		$schema = $this->mockSchema(new SqlitePlatform(), [
			'libresign_file' => $fileTable,
			'libresign_file_element' => $elementTable,
		]);

		$migration = new Version8000Date20240405142042($this->connection);
		$result = $migration->changeSchema($this->output, static fn () => $schema, []);

		self::assertNull($result);
	}

	public function testChangeSchemaSkipsTablesWithoutMetadataColumn(): void {
		$fileTable = $this->mockTable(false, null);
		$fileTable->expects($this->never())->method('modifyColumn');

		$elementTable = $this->mockTable(true, ColumnType::Text);
		$elementTable->expects($this->once())
			->method('modifyColumn')
			->with('metadata', ['type' => 'json']);

		$schema = $this->mockSchema(new SqlitePlatform(), [
			'libresign_file' => $fileTable,
			'libresign_file_element' => $elementTable,
		]);

		$migration = new Version8000Date20240405142042($this->connection);
		$result = $migration->changeSchema($this->output, static fn () => $schema, []);

		self::assertSame($schema, $result);
	}

	public function testChangeSchemaDefersToPostSchemaChangeOnPostgres(): void {
		$fileTable = $this->createMock(ITable::class);
		$fileTable->expects($this->never())->method('hasColumn');
		$fileTable->expects($this->never())->method('modifyColumn');

		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
		$schema->expects($this->never())->method('getTable');

		$migration = new Version8000Date20240405142042($this->connection);
		$result = $migration->changeSchema($this->output, static fn () => $schema, []);

		self::assertNull($result);
	}

	public function testPostSchemaChangeCastsLegacyColumnsOnPostgres(): void {
		$fileTable = $this->mockTable(true, ColumnType::Text);
		$elementTable = $this->mockTable(true, ColumnType::Text);

		$schema = $this->mockSchema(new PostgreSQLPlatform(), [
			'libresign_file' => $fileTable,
			'libresign_file_element' => $elementTable,
		]);

		$executedStatements = [];
		$this->connection->method('executeStatement')
			->willReturnCallback(function (string $sql) use (&$executedStatements): int {
				$executedStatements[] = $sql;
				return 0;
			});

		$migration = new Version8000Date20240405142042($this->connection);
		$migration->postSchemaChange($this->output, static fn () => $schema, []);

		self::assertContains('ALTER TABLE *PREFIX*libresign_file ALTER COLUMN metadata TYPE json USING metadata::json', $executedStatements);
		self::assertContains('ALTER TABLE *PREFIX*libresign_file_element ALTER COLUMN metadata TYPE json USING metadata::json', $executedStatements);
	}

	public function testPostSchemaChangeSkipsAlreadyJsonColumnsOnPostgres(): void {
		$fileTable = $this->mockTable(true, ColumnType::Json);
		$elementTable = $this->mockTable(true, ColumnType::Json);

		$schema = $this->mockSchema(new PostgreSQLPlatform(), [
			'libresign_file' => $fileTable,
			'libresign_file_element' => $elementTable,
		]);

		$this->connection->expects($this->never())->method('executeStatement');

		$migration = new Version8000Date20240405142042($this->connection);
		$migration->postSchemaChange($this->output, static fn () => $schema, []);
	}

	public function testPostSchemaChangeSkipsNonPostgresPlatform(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('getDatabasePlatform')->willReturn(new SqlitePlatform());
		$schema->expects($this->never())->method('getTable');

		$this->connection->expects($this->never())->method('executeStatement');

		$migration = new Version8000Date20240405142042($this->connection);
		$migration->postSchemaChange($this->output, static fn () => $schema, []);
	}
}
