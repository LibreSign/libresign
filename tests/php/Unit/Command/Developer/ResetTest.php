<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Command\Developer;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Command\Developer\Reset;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ResetTest extends TestCase {
	private IConfig&MockObject $config;
	private IAppConfig&MockObject $appConfig;
	private IDBConnection&MockObject $db;
	private LoggerInterface&MockObject $logger;
	private Reset $command;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->command = new Reset(
			$this->config,
			$this->appConfig,
			$this->db,
			$this->logger,
		);
	}

	public function testResetConfigDeletesAppKeysWithoutRestoringDocMdpLevel(): void {
		$deletedKeys = [];

		$this->appConfig->expects($this->once())
			->method('getKeys')
			->with(Application::APP_ID)
			->willReturn([
				'enabled',
				'installed_version',
				'docmdp_level',
				'signature_flow',
			]);

		$this->appConfig->expects($this->exactly(2))
			->method('deleteKey')
			->willReturnCallback(function (string $appId, string $key) use (&$deletedKeys): void {
				$this->assertSame(Application::APP_ID, $appId);
				$deletedKeys[] = $key;
			});

		$this->appConfig->expects($this->never())
			->method('setValueInt');

		$status = $this->command->run(new ArrayInput([
			'--config' => true,
		]), new BufferedOutput());

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertSame(['docmdp_level', 'signature_flow'], $deletedKeys);
	}

	public function testResetPolicyClearsPermissionSetsAndStoredUserPolicyPreferences(): void {
		$bindingDelete = $this->createMock(IQueryBuilder::class);
		$bindingDelete
			->expects($this->once())
			->method('delete')
			->with('libresign_permission_set_binding')
			->willReturnSelf();
		$bindingDelete
			->expects($this->once())
			->method('executeStatement');

		$permissionSetDelete = $this->createMock(IQueryBuilder::class);
		$permissionSetDelete
			->expects($this->once())
			->method('delete')
			->with('libresign_permission_set')
			->willReturnSelf();
		$permissionSetDelete
			->expects($this->once())
			->method('executeStatement');

		$expr = $this->createMock(IExpressionBuilder::class);
		$expr
			->expects($this->once())
			->method('eq')
			->with('appid', ':appId')
			->willReturn('appid = :appId');
		$expr
			->expects($this->once())
			->method('like')
			->with('configkey', ':policyPrefix')
			->willReturn('configkey LIKE :policyPrefix');

		$preferencesDelete = $this->createMock(IQueryBuilder::class);
		$preferencesDelete
			->expects($this->once())
			->method('delete')
			->with('preferences')
			->willReturnSelf();
		$preferencesDelete
			->expects($this->once())
			->method('where')
			->with('appid = :appId')
			->willReturnSelf();
		$preferencesDelete
			->expects($this->once())
			->method('andWhere')
			->with('configkey LIKE :policyPrefix')
			->willReturnSelf();
		$preferencesDelete
			->method('expr')
			->willReturn($expr);
		$preferencesDelete
			->method('createNamedParameter')
			->willReturnCallback(static function (mixed $value): string {
				return match ($value) {
					Application::APP_ID => ':appId',
					'policy.%' => ':policyPrefix',
					default => ':unexpected',
				};
			});
		$preferencesDelete
			->expects($this->once())
			->method('executeStatement');

		$this->db
			->expects($this->exactly(3))
			->method('getQueryBuilder')
			->willReturnOnConsecutiveCalls($bindingDelete, $permissionSetDelete, $preferencesDelete);

		$status = $this->command->run(new ArrayInput([
			'--policy' => true,
		]), new BufferedOutput());

		$this->assertSame(Command::SUCCESS, $status);
	}
}
