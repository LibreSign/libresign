<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Migration;

use OC\Migration\NullOutput;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Migration\Version19001Date20261022200000;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Server;

/**
 * @group DB
 */
final class Version19001Date20261022200000Test extends \OCA\Libresign\Tests\Integration\TestCase {
	private const FOOTER = '{"enabled":true,"writeQrcodeOnFooter":false,"validationSite":"https://test.example/","customizeFooterTemplate":true}';
	private const IDENTIFICATION = '{"enabled":true,"approvers":["legal","hr"]}';
	private const GROUPS = '{"allowGroups":["legal"],"denyGroups":["blocked"]}';

	private const KEYS = ['add_footer', 'identification_documents', 'groups_request_sign'];

	private IDBConnection $connection;
	private IAppConfig $appConfig;
	/** @var array<string, array<string, mixed>|null> */
	private array $originalRows = [];

	public function setUp(): void {
		parent::setUp();
		$this->connection = Server::get(IDBConnection::class);
		$this->appConfig = Server::get(IAppConfig::class);
		foreach (self::KEYS as $key) {
			$this->originalRows[$key] = $this->readRow($key);
			$this->deleteRow($key);
		}
		$this->appConfig->clearCache();
	}

	public function tearDown(): void {
		try {
			foreach (self::KEYS as $key) {
				$this->deleteRow($key);
				if ($this->originalRows[$key] !== null) {
					$this->insertRow(
						$key,
						(string)$this->originalRows[$key]['configvalue'],
						(int)$this->originalRows[$key]['type'],
						(int)$this->originalRows[$key]['lazy'],
					);
				}
			}
			$this->appConfig->clearCache();
		} finally {
			parent::tearDown();
		}
	}

	public function testRepairsMismatchedTypesWithoutChangingStoredValues(): void {
		$this->insertRow('add_footer', self::FOOTER, IAppConfig::VALUE_BOOL, 0);
		$this->insertRow('identification_documents', self::IDENTIFICATION, IAppConfig::VALUE_BOOL, 0);
		$this->insertRow('groups_request_sign', self::GROUPS, IAppConfig::VALUE_ARRAY, 0);
		$this->appConfig->clearCache();

		try {
			$this->appConfig->getValueString(Application::APP_ID, 'add_footer');
			self::fail('The invalid boolean metadata must produce a type conflict before migration.');
		} catch (AppConfigTypeConflictException) {
			// Reproduces the reported Nextcloud warning.
		}
		self::assertFalse($this->appConfig->getValueBool(Application::APP_ID, 'add_footer', true));

		$this->runMigration();
		$this->assertStored('add_footer', self::FOOTER, IAppConfig::VALUE_STRING);
		$this->assertStored('identification_documents', self::IDENTIFICATION, IAppConfig::VALUE_ARRAY);
		$this->assertStored('groups_request_sign', self::GROUPS, IAppConfig::VALUE_STRING);

		self::assertSame(self::FOOTER, $this->appConfig->getValueString(Application::APP_ID, 'add_footer'));
		self::assertSame(
			['enabled' => true, 'approvers' => ['legal', 'hr']],
			$this->appConfig->getValueArray(Application::APP_ID, 'identification_documents'),
		);
		self::assertSame(self::GROUPS, $this->appConfig->getValueString(Application::APP_ID, 'groups_request_sign'));

		$this->runMigration();
		$this->assertStored('add_footer', self::FOOTER, IAppConfig::VALUE_STRING);
		$this->assertStored('identification_documents', self::IDENTIFICATION, IAppConfig::VALUE_ARRAY);
		$this->assertStored('groups_request_sign', self::GROUPS, IAppConfig::VALUE_STRING);
	}

	public function testRepairsKnownJsonFromManualMixedTypeWorkaround(): void {
		$this->insertRow('add_footer', self::FOOTER, IAppConfig::VALUE_MIXED, 0);
		$this->runMigration();
		$this->assertStored('add_footer', self::FOOTER, IAppConfig::VALUE_STRING);
	}

	public function testLeavesLegacyAndUnknownValuesUntouched(): void {
		$this->insertRow('add_footer', '1', IAppConfig::VALUE_BOOL, 0);
		$this->insertRow('identification_documents', '{"unrecognized":true}', IAppConfig::VALUE_BOOL, 0);
		$this->insertRow('groups_request_sign', '{not valid JSON', IAppConfig::VALUE_ARRAY, 0);

		$this->runMigration();
		$this->assertStored('add_footer', '1', IAppConfig::VALUE_BOOL);
		$this->assertStored('identification_documents', '{"unrecognized":true}', IAppConfig::VALUE_BOOL);
		$this->assertStored('groups_request_sign', '{not valid JSON', IAppConfig::VALUE_ARRAY);
	}

	public function testLeavesAlreadyTypedValuesUntouched(): void {
		$this->insertRow('add_footer', self::FOOTER, IAppConfig::VALUE_STRING, 0);
		$this->insertRow('identification_documents', self::IDENTIFICATION, IAppConfig::VALUE_ARRAY, 0);
		$this->insertRow('groups_request_sign', self::GROUPS, IAppConfig::VALUE_STRING, 0);
		$this->runMigration();
		$this->assertStored('add_footer', self::FOOTER, IAppConfig::VALUE_STRING);
		$this->assertStored('identification_documents', self::IDENTIFICATION, IAppConfig::VALUE_ARRAY);
		$this->assertStored('groups_request_sign', self::GROUPS, IAppConfig::VALUE_STRING);
	}

	public function testKeepsLazyFlagWhileRepairingType(): void {
		$this->insertRow('groups_request_sign', self::GROUPS, IAppConfig::VALUE_ARRAY, 1);
		$this->runMigration();
		$this->assertStored('groups_request_sign', self::GROUPS, IAppConfig::VALUE_STRING, 1);
	}

	public function testSkipsJsonWithInvalidPolicyFields(): void {
		$this->insertRow('add_footer', '{"enabled":"yes"}', IAppConfig::VALUE_BOOL, 0);
		$this->insertRow('identification_documents', '{"enabled":true,"approvers":"admin"}', IAppConfig::VALUE_BOOL, 0);
		$this->insertRow('groups_request_sign', '{"allowGroups":[23]}', IAppConfig::VALUE_ARRAY, 0);
		$this->runMigration();
		$this->assertStored('add_footer', '{"enabled":"yes"}', IAppConfig::VALUE_BOOL);
		$this->assertStored('identification_documents', '{"enabled":true,"approvers":"admin"}', IAppConfig::VALUE_BOOL);
		$this->assertStored('groups_request_sign', '{"allowGroups":[23]}', IAppConfig::VALUE_ARRAY);
	}

	private function runMigration(): void {
		(new Version19001Date20261022200000($this->connection, $this->appConfig))
			->postSchemaChange(new NullOutput(), static fn () => null, []);
	}

	private function assertStored(string $key, string $raw, int $type, int $lazy = 0): void {
		$row = $this->readRow($key);
		self::assertNotNull($row);
		self::assertSame($raw, $row['configvalue']);
		self::assertSame($type, (int)$row['type']);
		self::assertSame($lazy, (int)$row['lazy']);
	}

	/** @return array<string, mixed>|null */
	private function readRow(string $key): ?array {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('configvalue', 'type', 'lazy')
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)));
		$row = $qb->executeQuery()->fetchAssociative();
		return $row === false ? null : $row;
	}

	private function deleteRow(string $key): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)))
			->executeStatement();
	}

	private function insertRow(string $key, string $raw, int $type, int $lazy): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('appconfig')
			->values([
				'appid' => $qb->createNamedParameter(Application::APP_ID),
				'configkey' => $qb->createNamedParameter($key),
				'configvalue' => $qb->createNamedParameter($raw),
				'type' => $qb->createNamedParameter($type),
				'lazy' => $qb->createNamedParameter($lazy),
			])
			->executeStatement();
	}
}
