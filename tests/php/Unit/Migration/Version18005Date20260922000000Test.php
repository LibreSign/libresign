<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\PermissionSet;
use OCA\Libresign\Db\PermissionSetMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Migration\Version18005Date20260922000000;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Server;

/**
 * @group DB
 */
final class Version18005Date20260922000000Test extends \OCA\Libresign\Tests\Unit\TestCase {
	private const CONFIG_KEYS = [
		'signer_geolocation',
		'signer_geolocation.allow_child_override',
		'signer_device_geolocation',
		'signer_device_geolocation.allow_child_override',
		'policy.signer_geolocation',
		'policy.signer_geolocation.assigned',
		'policy.signer_device_geolocation',
		'policy.signer_device_geolocation.assigned',
	];

	private IDBConnection $connection;
	private IAppConfig $appConfig;
	/** @var list<int> */
	private array $permissionSetIds = [];

	public function setUp(): void {
		parent::setUp();
		$this->connection = Server::get(IDBConnection::class);
		$this->appConfig = Server::get(IAppConfig::class);
		$this->deleteConfigRows();
	}

	public function tearDown(): void {
		$this->deleteConfigRows();
		foreach ($this->permissionSetIds as $id) {
			$delete = $this->connection->getQueryBuilder();
			$delete->delete('libresign_permission_set')
				->where($delete->expr()->eq('id', $delete->createNamedParameter($id)))
				->executeStatement();
		}
		$this->appConfig->clearCache();
		parent::tearDown();
	}

	public function testRenamesSystemPolicyAppConfigKeys(): void {
		$this->insertAppConfig('signer_geolocation', '{"mode":"required"}');
		$this->insertAppConfig('signer_geolocation.allow_child_override', '1');

		$this->runMigration();

		$this->assertNull($this->readAppConfig('signer_geolocation'));
		$this->assertNull($this->readAppConfig('signer_geolocation.allow_child_override'));
		$this->assertSame('{"mode":"required"}', $this->readAppConfig('signer_device_geolocation'));
		$this->assertSame('1', $this->readAppConfig('signer_device_geolocation.allow_child_override'));
	}

	public function testKeepsExistingNewAppConfigKeyAndDropsLegacyOne(): void {
		$this->insertAppConfig('signer_geolocation', '{"mode":"optional"}');
		$this->insertAppConfig('signer_device_geolocation', '{"mode":"required"}');

		$this->runMigration();

		$this->assertNull($this->readAppConfig('signer_geolocation'));
		$this->assertSame('{"mode":"required"}', $this->readAppConfig('signer_device_geolocation'));
	}

	public function testRenamesUserPreferencesWithoutOverwritingExistingOnes(): void {
		$this->insertPreference('alice', 'policy.signer_geolocation', '{"mode":"optional"}');
		$this->insertPreference('alice', 'policy.signer_geolocation.assigned', '{"value":{"mode":"required"},"allowChildOverride":false}');
		$this->insertPreference('bob', 'policy.signer_geolocation', '{"mode":"optional"}');
		$this->insertPreference('bob', 'policy.signer_device_geolocation', '{"mode":"disabled"}');

		$this->runMigration();

		$this->assertSame('{"mode":"optional"}', $this->readPreference('alice', 'policy.signer_device_geolocation'));
		$this->assertSame(
			'{"value":{"mode":"required"},"allowChildOverride":false}',
			$this->readPreference('alice', 'policy.signer_device_geolocation.assigned'),
		);
		$this->assertSame('{"mode":"disabled"}', $this->readPreference('bob', 'policy.signer_device_geolocation'));
		$this->assertNull($this->readPreference('alice', 'policy.signer_geolocation'));
		$this->assertNull($this->readPreference('alice', 'policy.signer_geolocation.assigned'));
		$this->assertNull($this->readPreference('bob', 'policy.signer_geolocation'));
	}

	public function testRenamesGroupPolicyAndDelegatedOverride(): void {
		$permissionSet = new PermissionSet();
		$permissionSet->setName('group:migration-test');
		$permissionSet->setScopeType('group');
		$permissionSet->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$permissionSet->setUpdatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$permissionSet->setPolicyJson([
			'signer_geolocation' => ['defaultValue' => ['mode' => 'optional'], 'allowChildOverride' => true],
			'signer_geolocation__delegated_override' => ['defaultValue' => ['mode' => 'required'], 'allowChildOverride' => false],
			'enable_observer_profile' => ['defaultValue' => true],
		]);
		$permissionSetMapper = Server::get(PermissionSetMapper::class);
		$permissionSet = $permissionSetMapper->insert($permissionSet);
		$this->permissionSetIds[] = $permissionSet->getId();

		$this->runMigration();

		$this->assertSame(
			$this->sortedKeys([
				'enable_observer_profile' => ['defaultValue' => true],
				'signer_device_geolocation' => ['defaultValue' => ['mode' => 'optional'], 'allowChildOverride' => true],
				'signer_device_geolocation__delegated_override' => ['defaultValue' => ['mode' => 'required'], 'allowChildOverride' => false],
			]),
			$this->sortedKeys($this->readPermissionSetPolicyJson($permissionSet->getId())),
		);
	}

	public function testRenamesFrozenFilePolicySnapshot(): void {
		$file = $this->insertFile([
			'policy_snapshot' => [
				'signer_geolocation' => ['effectiveValue' => ['mode' => 'required'], 'sourceScope' => 'system'],
				'enable_observer_profile' => ['effectiveValue' => true, 'sourceScope' => 'system'],
			],
		]);

		$this->runMigration();

		$metadata = $this->readMetadata('libresign_file', $file->getId());
		$this->assertSame(
			$this->sortedKeys([
				'enable_observer_profile' => ['effectiveValue' => true, 'sourceScope' => 'system'],
				'signer_device_geolocation' => ['effectiveValue' => ['mode' => 'required'], 'sourceScope' => 'system'],
			]),
			$this->sortedKeys($metadata['policy_snapshot']),
		);
	}

	public function testRenamesSignerRequirementAndNestsFlatDeviceGeolocation(): void {
		$signRequest = $this->insertSignRequest($this->insertFile([]), [
			'geolocationRequirement' => 'required',
			'geolocation' => ['status' => 'collected', 'latitude' => -23.5, 'longitude' => -46.6],
			'user-agent' => 'test',
		]);

		$this->runMigration();
		$this->runMigration();

		$this->assertSame(
			$this->sortedKeys([
				'deviceGeolocationRequirement' => 'required',
				'geolocation' => [
					'device' => ['status' => 'collected', 'latitude' => -23.5, 'longitude' => -46.6],
				],
				'user-agent' => 'test',
			]),
			$this->sortedKeys($this->readMetadata('libresign_sign_request', $signRequest->getId())),
		);
	}

	/** @return array<string, mixed> */
	private function readMetadata(string $table, int $id): array {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('metadata')
			->from($table)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		return json_decode((string)$qb->executeQuery()->fetchOne(), true);
	}

	private function runMigration(): void {
		$migration = new Version18005Date20260922000000($this->appConfig, $this->connection);
		$migration->postSchemaChange(
			$this->createMock(IOutput::class),
			fn (): ISchemaWrapper => $this->createMock(ISchemaWrapper::class),
			[],
		);
	}

	private function insertAppConfig(string $key, string $value): void {
		$insert = $this->connection->getQueryBuilder();
		$insert->insert('appconfig')
			->values([
				'appid' => $insert->createNamedParameter(Application::APP_ID),
				'configkey' => $insert->createNamedParameter($key),
				'configvalue' => $insert->createNamedParameter($value),
			])
			->executeStatement();
	}

	private function readAppConfig(string $key): ?string {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('configvalue')
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)));
		$value = $qb->executeQuery()->fetchOne();
		return $value === false ? null : (string)$value;
	}

	private function insertPreference(string $userId, string $key, string $value): void {
		$insert = $this->connection->getQueryBuilder();
		$insert->insert('preferences')
			->values([
				'userid' => $insert->createNamedParameter($userId),
				'appid' => $insert->createNamedParameter(Application::APP_ID),
				'configkey' => $insert->createNamedParameter($key),
				'configvalue' => $insert->createNamedParameter($value),
			])
			->executeStatement();
	}

	private function readPreference(string $userId, string $key): ?string {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('configvalue')
			->from('preferences')
			->where($qb->expr()->eq('userid', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)));
		$value = $qb->executeQuery()->fetchOne();
		return $value === false ? null : (string)$value;
	}

	private function deleteConfigRows(): void {
		foreach (['appconfig', 'preferences'] as $table) {
			$delete = $this->connection->getQueryBuilder();
			$delete->delete($table)
				->where($delete->expr()->eq('appid', $delete->createNamedParameter(Application::APP_ID)))
				->andWhere($delete->expr()->in('configkey', $delete->createNamedParameter(self::CONFIG_KEYS, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR_ARRAY)))
				->executeStatement();
		}
	}

	/** @return array<string, mixed> */
	private function readPermissionSetPolicyJson(int $id): array {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('policy_json')
			->from('libresign_permission_set')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
		return json_decode((string)$qb->executeQuery()->fetchOne(), true);
	}

	private function insertFile(array $metadata): File {
		$file = new File();
		$file->setNodeId(random_int(100000, 999999));
		$file->setUserId('admin');
		$file->setUuid(\Sabre\DAV\UUIDUtil::getUUID());
		$file->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$file->setName('geolocation-migration');
		$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		$file->setMetadata($metadata);
		return Server::get(FileMapper::class)->insert($file);
	}

	private function insertSignRequest(File $file, array $metadata): SignRequest {
		$signRequest = new SignRequest();
		$signRequest->setFileId($file->getId());
		$signRequest->setUuid(\Sabre\DAV\UUIDUtil::getUUID());
		$signRequest->setDisplayName('Signer');
		$signRequest->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$signRequest->setMetadata($metadata);
		return Server::get(SignRequestMapper::class)->insert($signRequest);
	}

	/**
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	private function sortedKeys(array $values): array {
		foreach ($values as $key => $value) {
			if (is_array($value)) {
				$values[$key] = $this->sortedKeys($value);
			}
		}
		ksort($values);
		return $values;
	}
}
