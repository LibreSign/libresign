<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Migration\Version19005Date20261109110000;
use OCP\DB\IResult;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Schema\ITable;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

final class Version19005Date20261109110000Test extends TestCase {
	public function testDropsCallbackColumnOnlyOnce(): void {
		$table = $this->createMock(ITable::class);
		$table->expects($this->exactly(2))->method('hasColumn')->with('callback')
			->willReturnOnConsecutiveCalls(true, false);
		$table->expects($this->once())->method('dropColumn')->with('callback');
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('libresign_file')->willReturn(true);
		$schema->method('getTable')->with('libresign_file')->willReturn($table);
		$output = $this->createMock(IOutput::class);
		$migration = $this->migration($this->createMock(IAppConfig::class));

		$this->assertSame($schema, $migration->changeSchema($output, static fn (): ISchemaWrapper => $schema, []));
		$this->assertNull($migration->changeSchema($output, static fn (): ISchemaWrapper => $schema, []));
	}

	public function testSkipsMissingFileTable(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('libresign_file')->willReturn(false);

		$this->assertNull($this->migration($this->createMock(IAppConfig::class))->changeSchema(
			$this->createMock(IOutput::class),
			static fn (): ISchemaWrapper => $schema,
			[],
		));
	}

	public function testBacksUpOnlyConfiguredCallbacksBeforeDroppingColumn(): void {
		$rows = [
			['id' => '12', 'callback' => 'https://example.test/a?a=1,b=2'],
			['id' => '47', 'callback' => 'https://example.test/ç?x="quoted"'],
		];
		$csvContent = '';
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->never())->method('write');
		$file->method('getSize')->willReturnCallback(static function () use (&$csvContent): int {
			return strlen($csvContent);
		});
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->expects($this->once())
			->method('newFile')
			->with(
				'backup-table-libresign_file_callback_Version19005Date20261109110000.csv',
				$this->callback(static function ($content) use (&$csvContent): bool {
					if (!is_string($content)) {
						return false;
					}
					$csvContent = $content;
					return true;
				}),
			)->willReturn($file);
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->with('/')->willReturn($folder);
		$factory = $this->createMock(IAppDataFactory::class);
		$factory->method('get')->with(Application::APP_ID)->willReturn($appData);
		$output = $this->createMock(IOutput::class);
		$output->expects($this->once())->method('info')
			->with($this->stringContains('backup-table-libresign_file_callback_'));
		$migration = $this->migration($this->createMock(IAppConfig::class), $factory, $this->connectionForRows($rows));
		$this->runPreSchema($migration, true, $output);

		$fp = fopen('php://temp', 'w+');
		$this->assertIsResource($fp);
		try {
			fwrite($fp, $csvContent);
			rewind($fp);
			$this->assertSame(['id', 'callback'], fgetcsv($fp, 0, ',', '"', ''));
			$this->assertSame(array_values($rows[0]), fgetcsv($fp, 0, ',', '"', ''));
			$this->assertSame(array_values($rows[1]), fgetcsv($fp, 0, ',', '"', ''));
			$this->assertFalse(fgetcsv($fp, 0, ',', '"', ''));
		} finally {
			fclose($fp);
		}
	}

	public function testCallbackBackupFailureStopsMigrationWithoutRemovingData(): void {
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->expects($this->once())->method('newFile')
			->willThrowException(new \RuntimeException('Backup file already exists'));
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willReturn($folder);
		$factory = $this->createMock(IAppDataFactory::class);
		$factory->method('get')->willReturn($appData);
		$config = $this->createMock(IAppConfig::class);
		$config->expects($this->never())->method('deleteKey');
		$migration = $this->migration($config, $factory, $this->connectionForRows([
			['id' => '4', 'callback' => 'https://example.test/callback'],
		]));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Backup file already exists');
		$this->runPreSchema($migration, true);
	}

	public function testTruncatedCallbackBackupAbortsMigration(): void {
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getSize')->willReturn(1);
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->expects($this->once())->method('newFile')->willReturn($file);
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willReturn($folder);
		$factory = $this->createMock(IAppDataFactory::class);
		$factory->method('get')->willReturn($appData);
		$config = $this->createMock(IAppConfig::class);
		$config->expects($this->never())->method('deleteKey');
		$output = $this->createMock(IOutput::class);
		$output->expects($this->never())->method('info');
		$migration = $this->migration($config, $factory, $this->connectionForRows([
			['id' => '4', 'callback' => 'https://example.test/callback'],
		]));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Could not verify LibreSign callback backup size');
		$this->runPreSchema($migration, true, $output);
	}

	public function testNoBackupFileWhenAllCallbacksAreEmpty(): void {
		$appData = $this->createMock(IAppData::class);
		$appData->expects($this->never())->method('getFolder');
		$factory = $this->createMock(IAppDataFactory::class);
		$factory->method('get')->willReturn($appData);
		$migration = $this->migration($this->createMock(IAppConfig::class), $factory, $this->connectionForRows([]));
		$output = $this->createMock(IOutput::class);
		$output->expects($this->never())->method('info');
		$this->runPreSchema($migration, true, $output);
	}

	public function testBacksUpGlobalCallbackEvenWhenColumnIsAbsent(): void {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getKeys')->willReturn(['webhook_sign_url']);
		$config->method('getValueString')->with(Application::APP_ID, 'webhook_sign_url')
			->willReturn('https://example.test/callback?secret=abc');
		$expectedJson = "{\n    \"webhook_sign_url\": \"https://example.test/callback?secret=abc\"\n}";
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getSize')->willReturn(strlen($expectedJson));
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->expects($this->once())->method('newFile')
			->with('backup-config-webhook_sign_url_Version19005Date20261109110000.json', $expectedJson)
			->willReturn($file);
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->with('/')->willReturn($folder);
		$factory = $this->createMock(IAppDataFactory::class);
		$factory->method('get')->willReturn($appData);
		$output = $this->createMock(IOutput::class);
		$output->expects($this->once())->method('info')->with($this->callback(static function (string $message): bool {
			return str_contains($message, 'backup-config-webhook_sign_url_') && !str_contains($message, 'secret=abc');
		}));

		$this->runPreSchema($this->migration($config, $factory), false, $output);
	}

	public function testDoesNotDeleteConfigWhenBackupFails(): void {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getKeys')->willReturn(['webhook_sign_url']);
		$config->method('getValueString')->willReturn('https://example.test/callback');
		$config->expects($this->never())->method('deleteKey');
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('newFile')->willThrowException(new \RuntimeException('AppData unavailable'));
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willReturn($folder);
		$factory = $this->createMock(IAppDataFactory::class);
		$factory->method('get')->willReturn($appData);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('AppData unavailable');
		$this->runPreSchema($this->migration($config, $factory), false);
	}

	public function testTruncatedConfigBackupDoesNotDeleteLegacyValue(): void {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getKeys')->willReturn(['webhook_sign_url']);
		$config->method('getValueString')->willReturn('https://example.test/callback');
		$config->expects($this->never())->method('deleteKey');
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getSize')->willReturn(1);
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('newFile')->willReturn($file);
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willReturn($folder);
		$factory = $this->createMock(IAppDataFactory::class);
		$factory->method('get')->willReturn($appData);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Could not verify LibreSign webhook URL backup size');
		$this->runPreSchema($this->migration($config, $factory), false);
	}

	public function testRemovesOnlyConfiguredLegacyWebhookUrl(): void {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getKeys')->with(Application::APP_ID)->willReturn(['webhook_sign_url', 'signature_engine']);
		$config->expects($this->once())->method('deleteKey')->with(Application::APP_ID, 'webhook_sign_url');
		$this->migration($config)->postSchemaChange($this->createMock(IOutput::class), static fn (): ISchemaWrapper => throw new \LogicException('Schema unused'), []);
	}

	public function testLeavesConfigUntouchedWhenLegacyKeyIsMissing(): void {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getKeys')->with(Application::APP_ID)->willReturn(['signature_engine']);
		$config->expects($this->never())->method('deleteKey');
		$this->migration($config)->postSchemaChange($this->createMock(IOutput::class), static fn (): ISchemaWrapper => throw new \LogicException('Schema unused'), []);
	}

	private function runPreSchema(Version19005Date20261109110000 $migration, bool $columnExists, ?IOutput $output = null): void {
		$table = $this->createMock(ITable::class);
		$table->method('hasColumn')->with('callback')->willReturn($columnExists);
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('libresign_file')->willReturn(true);
		$schema->method('getTable')->willReturn($table);
		$migration->preSchemaChange($output ?? $this->createMock(IOutput::class), static fn (): ISchemaWrapper => $schema, []);
	}

	private function connectionForRows(array $rows): IDBConnection {
		$result = $this->createMock(IResult::class);
		$result->method('fetchAssociative')->willReturnOnConsecutiveCalls(...[...$rows, false]);
		$result->expects($this->once())->method('closeCursor');
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->expects($this->once())->method('nonEmptyString')->with('callback')
			->willReturn('portable non-empty callback predicate');
		$query = $this->createMock(IQueryBuilder::class);
		$query->expects($this->once())->method('select')->with('id', 'callback')->willReturnSelf();
		$query->expects($this->once())->method('where')->with('portable non-empty callback predicate')->willReturnSelf();
		$query->expects($this->never())->method('andWhere');
		foreach (['from', 'orderBy'] as $method) {
			$query->method($method)->willReturnSelf();
		}
		$query->method('expr')->willReturn($expr);
		$query->method('executeQuery')->willReturn($result);
		$connection = $this->createMock(IDBConnection::class);
		$connection->method('getQueryBuilder')->willReturn($query);
		return $connection;
	}

	private function migration(IAppConfig $config, ?IAppDataFactory $factory = null, ?IDBConnection $connection = null): Version19005Date20261109110000 {
		$factory ??= $this->createMock(IAppDataFactory::class);
		$connection ??= $this->createMock(IDBConnection::class);
		return new Version19005Date20261109110000($config, $connection, $factory);
	}
}
