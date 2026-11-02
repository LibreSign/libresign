<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Migration;

use OC\Migration\NullOutput;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Migration\Version19004Date20261027180000;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyValue;
use OCA\Libresign\Service\Policy\Runtime\PolicySource;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @group DB
 */
final class Version19004Date20261027180000Test extends \Test\TestCase {
	private const VALUES = [
		'add_footer' => '{ "enabled": true, "writeQrcodeOnFooter": false }',
		'identification_documents' => '{"enabled":true,"approvers":["legal"]}',
		'groups_request_sign' => '{"allowGroups":["legal"],"denyGroups":[]}',
	];

	private const OVERRIDE_KEYS = [
		'add_footer.allow_child_override',
		'identification_documents.allow_child_override',
		'groups_request_sign.allow_child_override',
	];

	private const EXPECTED_TYPES = [
		'add_footer' => IAppConfig::VALUE_STRING,
		'identification_documents' => IAppConfig::VALUE_ARRAY,
		'groups_request_sign' => IAppConfig::VALUE_STRING,
	];

	private IDBConnection $connection;
	private IAppConfig $appConfig;

	/** @var array<string, array<string, mixed>|null> */
	private array $originalRows = [];
	private bool $insertedPreviousMigration = false;

	public function setUp(): void {
		parent::setUp();
		$this->connection = Server::get(IDBConnection::class);
		$this->appConfig = Server::get(IAppConfig::class);

		foreach ([...array_keys(self::VALUES), ...self::OVERRIDE_KEYS] as $key) {
			$this->originalRows[$key] = $this->readRow($key);
			$this->deleteRow($key);
		}

		$this->appConfig->clearCache();
	}

	public function tearDown(): void {
		try {
			foreach ($this->originalRows as $key => $row) {
				$this->deleteRow($key);
				if ($row !== null) {
					$this->insertRow($key, (string)$row['configvalue'], (int)$row['type'], (int)$row['lazy']);
				}
			}
			if ($this->insertedPreviousMigration) {
				$qb = $this->connection->getQueryBuilder();
				$qb->delete('migrations')
					->where($qb->expr()->eq('app', $qb->createNamedParameter(Application::APP_ID)))
					->andWhere($qb->expr()->eq('version', $qb->createNamedParameter('19001Date20261022200000')))
					->executeStatement();
			}
			$this->appConfig->clearCache();
		} finally {
			parent::tearDown();
		}
	}

	public function testRepairsRegisteredPreviousMigrationStateWithoutChangingAnyValues(): void {
		$qb = $this->connection->getQueryBuilder();
		$previous = $qb->select('version')->from('migrations')
			->where($qb->expr()->eq('app', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter('19001Date20261022200000')))
			->executeQuery()->fetchOne();
		if ($previous === false) {
			$insert = $this->connection->getQueryBuilder();
			$insert->insert('migrations')->values([
				'app' => $insert->createNamedParameter(Application::APP_ID),
				'version' => $insert->createNamedParameter('19001Date20261022200000'),
			])->executeStatement();
			$this->insertedPreviousMigration = true;
		}
		self::assertSame('19001Date20261022200000', $qb->executeQuery()->fetchOne());

		$this->insertRow('add_footer', self::VALUES['add_footer'], IAppConfig::VALUE_BOOL, 0);
		$this->insertRow('identification_documents', self::VALUES['identification_documents'], IAppConfig::VALUE_BOOL, 1);
		$this->insertRow('groups_request_sign', self::VALUES['groups_request_sign'], IAppConfig::VALUE_ARRAY, 0);
		$before = $this->readSnapshot();

		$this->appConfig->clearCache();
		$this->expectConfigConflict('add_footer');

		$this->runMigration();

		foreach (self::EXPECTED_TYPES as $key => $type) {
			$row = $this->readRow($key);
			self::assertNotNull($row);
			self::assertSame($type, (int)$row['type']);
			self::assertSame($before[$key]['configvalue'], $row['configvalue']);
			self::assertSame(hash('sha256', (string)$before[$key]['configvalue']), hash('sha256', (string)$row['configvalue']));
			self::assertSame($before[$key]['lazy'], $row['lazy']);
		}

		self::assertSame(['enabled' => true, 'approvers' => ['legal']],
			$this->appConfig->getValueArray(Application::APP_ID, 'identification_documents', [], true));
		self::assertSame(self::VALUES['add_footer'], $this->appConfig->getValueString(Application::APP_ID, 'add_footer'));
		self::assertSame(self::VALUES['groups_request_sign'], $this->appConfig->getValueString(Application::APP_ID, 'groups_request_sign'));

		$afterFirstRun = $this->readSnapshot();
		$this->runMigration();
		self::assertSame($afterFirstRun, $this->readSnapshot());
	}

	public function testPostgresSecondRunDoesNotRewriteTheTuple(): void {
		if (Server::get(IConfig::class)->getSystemValue('dbtype', '') !== 'pgsql') {
			self::markTestSkipped('PostgreSQL-specific physical-row verification');
		}
		$this->insertRow('groups_request_sign', self::VALUES['groups_request_sign'], IAppConfig::VALUE_ARRAY, 0);
		$this->runMigration();
		$qb = $this->connection->getQueryBuilder();
		$qb->select('ctid', 'xmin')->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter('groups_request_sign')));
		$physicalRow = $qb->executeQuery()->fetchAssociative();

		$this->runMigration();

		self::assertSame($physicalRow, $qb->executeQuery()->fetchAssociative());
	}

	public function testLoadsAndSavesPoliciesWithTheirDeclaredTypesAfterRepair(): void {
		foreach (self::VALUES as $key => $value) {
			$this->insertRow($key, $value, $key === 'groups_request_sign' ? IAppConfig::VALUE_ARRAY : IAppConfig::VALUE_BOOL, 0);
		}
		$this->appConfig->clearCache();
		$this->runMigration();

		$source = Server::get(PolicySource::class);
		$footer = $source->loadSystemPolicy('add_footer');
		$identification = $source->loadSystemPolicy('identification_documents');
		$groups = $source->loadSystemPolicy('groups_request_sign');

		self::assertTrue(FooterPolicyValue::normalize($footer->getValue())['enabled']);
		self::assertSame(['legal'], $identification->getValue()['approvers'] ?? null);
		self::assertSame(['legal'], RequestSignGroupsPolicyValue::decodePolicy($groups->getValue())['allowGroups']);

		$source->saveSystemPolicy('add_footer', '{"enabled":false}', true);
		$source->saveSystemPolicy('identification_documents', ['enabled' => false, 'approvers' => ['admin']], true);
		$source->saveSystemPolicy('groups_request_sign', '{"allowGroups":["admin"],"denyGroups":[]}', false);

		foreach (self::EXPECTED_TYPES as $key => $type) {
			self::assertSame($type, (int)$this->readRow($key)['type']);
			self::assertNotNull($source->loadSystemPolicy($key));
		}
		self::assertSame(['admin'], $this->appConfig->getValueArray(Application::APP_ID, 'identification_documents')['approvers']);
	}

	public function testPreservesSensitiveBitWithoutChangingValueOrLazyFlag(): void {
		$original = self::VALUES['add_footer'];
		$type = IAppConfig::VALUE_BOOL | IAppConfig::VALUE_SENSITIVE;
		$this->insertRow('add_footer', $original, $type, 1);

		$this->runMigration();

		self::assertSame([
			'configvalue' => $original,
			'type' => IAppConfig::VALUE_STRING | IAppConfig::VALUE_SENSITIVE,
			'lazy' => 1,
		], $this->readRow('add_footer'));
	}

	public function testMissingKeysAndAlreadyCorrectKeysAreLeftUntouched(): void {
		$this->runMigration();
		self::assertNull($this->readRow('add_footer'));
		$this->insertRow('add_footer', self::VALUES['add_footer'], IAppConfig::VALUE_STRING, 1);
		$this->insertRow('groups_request_sign', self::VALUES['groups_request_sign'], IAppConfig::VALUE_STRING, 0);
		$before = $this->readSnapshot();
		$this->runMigration();
		self::assertSame($before, $this->readSnapshot());
		self::assertNull($this->readRow('identification_documents'));
	}

	#[DataProvider('unsafeValues')]
	public function testSkipsMalformedOrNonContainerValues(string $key, string $value, int $type): void {
		$this->insertRow($key, $value, $type, 1);
		$before = $this->readRow($key);

		$this->runMigration();

		self::assertSame($before, $this->readRow($key));
	}

	public static function unsafeValues(): array {
		return [
			'malformed object' => ['groups_request_sign', '{bad json', IAppConfig::VALUE_ARRAY],
			'JSON string' => ['identification_documents', '"legacy"', IAppConfig::VALUE_BOOL],
			'JSON true' => ['add_footer', 'true', IAppConfig::VALUE_BOOL],
			'JSON null' => ['groups_request_sign', 'null', IAppConfig::VALUE_ARRAY],
			'legacy boolean' => ['add_footer', '1', IAppConfig::VALUE_BOOL],
			'opaque encrypted payload' => ['add_footer', 'encrypted:opaque', IAppConfig::VALUE_BOOL | IAppConfig::VALUE_SENSITIVE],
		];
	}

	public function testDoesNotChangeValuesFromAnotherApplication(): void {
		$foreignApp = 'ls9100_metadata_other_app';
		self::assertNull($this->readRow('add_footer', $foreignApp));
		$this->insertRow('add_footer', self::VALUES['add_footer'], IAppConfig::VALUE_BOOL, 0, $foreignApp);
		try {
			$before = $this->readRow('add_footer', $foreignApp);
			$this->runMigration();
			self::assertSame($before, $this->readRow('add_footer', $foreignApp));
		} finally {
			$this->deleteRow('add_footer', $foreignApp);
		}
	}

	private function expectConfigConflict(string $key): void {
		try {
			$this->appConfig->getValueString(Application::APP_ID, $key);
			self::fail('Expected type conflict before repair');
		} catch (AppConfigTypeConflictException) {
			// The stale boolean metadata is reproduced.
		}
	}

	private function runMigration(): void {
		(new Version19004Date20261027180000($this->connection, $this->appConfig))
			->postSchemaChange(new NullOutput(), static fn () => null, []);
	}

	/** @return array<string, array<string, mixed>|null> */
	private function readSnapshot(): array {
		$rows = [];
		foreach (array_keys(self::VALUES) as $key) {
			$rows[$key] = $this->readRow($key);
		}
		return $rows;
	}

	/** @return array<string, mixed>|null */
	private function readRow(string $key, string $appId = Application::APP_ID): ?array {
		$qb = $this->connection->getQueryBuilder();
		$row = $qb->select('configvalue', 'type', 'lazy')
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter($appId)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)))
			->executeQuery()->fetchAssociative();
		return $row === false ? null : $row;
	}

	private function deleteRow(string $key, string $appId = Application::APP_ID): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter($appId)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)))
			->executeStatement();
	}

	private function insertRow(string $key, string $value, int $type, int $lazy, string $appId = Application::APP_ID): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('appconfig')->values([
			'appid' => $qb->createNamedParameter($appId),
			'configkey' => $qb->createNamedParameter($key),
			'configvalue' => $qb->createNamedParameter($value),
			'type' => $qb->createNamedParameter($type),
			'lazy' => $qb->createNamedParameter($lazy),
		])->executeStatement();
	}
}
