<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Table;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Migration\Version19005Date20261109110000;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

final class Version19005Date20261109110000Test extends TestCase {
	public function testDropsCallbackColumnOnlyOnce(): void {
		$table = new Table('libresign_file');
		$table->addColumn('callback', 'string', ['notnull' => false]);
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('libresign_file')->willReturn(true);
		$schema->method('getTable')->with('libresign_file')->willReturn($table);
		$output = $this->createMock(IOutput::class);
		$migration = new Version19005Date20261109110000($this->createMock(IAppConfig::class));

		$this->assertSame($schema, $migration->changeSchema($output, static fn (): ISchemaWrapper => $schema, []));
		$this->assertFalse($table->hasColumn('callback'));
		$this->assertNull($migration->changeSchema($output, static fn (): ISchemaWrapper => $schema, []));
	}

	public function testSkipsMissingFileTable(): void {
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('libresign_file')->willReturn(false);
		$migration = new Version19005Date20261109110000($this->createMock(IAppConfig::class));

		$this->assertNull($migration->changeSchema($this->createMock(IOutput::class), static fn (): ISchemaWrapper => $schema, []));
	}

	public function testRemovesOnlyConfiguredLegacyWebhookUrl(): void {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getKeys')->with(Application::APP_ID)->willReturn(['webhook_sign_url', 'signature_engine']);
		$config->expects($this->once())->method('deleteKey')->with(Application::APP_ID, 'webhook_sign_url');
		$migration = new Version19005Date20261109110000($config);

		$migration->postSchemaChange($this->createMock(IOutput::class), static fn (): ISchemaWrapper => throw new \LogicException('Schema unused'), []);
	}

	public function testLeavesConfigUntouchedWhenLegacyKeyIsMissing(): void {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getKeys')->with(Application::APP_ID)->willReturn(['signature_engine']);
		$config->expects($this->never())->method('deleteKey');
		$migration = new Version19005Date20261109110000($config);

		$migration->postSchemaChange($this->createMock(IOutput::class), static fn (): ISchemaWrapper => throw new \LogicException('Schema unused'), []);
	}
}
