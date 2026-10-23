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
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicyValue;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyValue;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;

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

	/**
	 * Repair the stored type for payloads accepted by the policy readers, even
	 * when the JSON was written by an older LibreSign version.
	 */
	public static function supportedPolicyPayloads(): array {
		return [
			'footer with legacy enabled string' => [
				'add_footer', '{"enabled":"yes"}', IAppConfig::VALUE_BOOL, IAppConfig::VALUE_STRING, ['enabled' => true],
			],
			'footer with only preview size' => [
				'add_footer', '{"previewWidth":640}', IAppConfig::VALUE_BOOL, IAppConfig::VALUE_STRING, ['previewWidth' => 640],
			],
			'footer with only custom template' => [
				'add_footer', '{"footerTemplate":"custom"}', IAppConfig::VALUE_BOOL, IAppConfig::VALUE_STRING, ['footerTemplate' => 'custom'],
			],
			'identification with associative approvers' => [
				'identification_documents', '{"enabled":true,"approvers":{"group":"legal"}}', IAppConfig::VALUE_BOOL, IAppConfig::VALUE_ARRAY,
				['enabled' => true, 'approvers' => ['legal']],
			],
			'groups with legacy list' => [
				'groups_request_sign', '["legal"]', IAppConfig::VALUE_ARRAY, IAppConfig::VALUE_STRING,
				['allowGroups' => ['legal'], 'denyGroups' => []],
			],
			'footer with future fields' => [
				'add_footer', '{"newField":"example"}', IAppConfig::VALUE_BOOL, IAppConfig::VALUE_STRING, ['enabled' => true],
			],
		];
	}

	#[DataProvider('supportedPolicyPayloads')]
	public function testRepairsTypeForPolicyJsonAndRetainsMeaning(
		string $key,
		string $raw,
		int $storedType,
		int $expectedType,
		array $expectedPolicy,
	): void {
		$this->insertRow($key, $raw, $storedType, 0);

		$this->runMigration();
		$this->assertStored($key, $raw, $expectedType);

		if ($key === 'identification_documents') {
			self::assertSame(
				$expectedPolicy,
				IdentificationDocumentsPolicyValue::normalize(
					$this->appConfig->getValueArray(Application::APP_ID, $key),
				),
			);
			return;
		}

		$read = $this->appConfig->getValueString(Application::APP_ID, $key);
		self::assertSame($raw, $read);

		if ($key === 'groups_request_sign') {
			self::assertSame($expectedPolicy, RequestSignGroupsPolicyValue::decodePolicy($read));
			return;
		}

		$actual = FooterPolicyValue::normalize($read);
		self::assertSame($expectedPolicy, array_intersect_key($actual, $expectedPolicy));
	}

	public function testRepairsAllThreeKeysAfterManualMixedTypeWorkaround(): void {
		$this->insertRow('add_footer', self::FOOTER, IAppConfig::VALUE_MIXED, 0);
		$this->insertRow('identification_documents', self::IDENTIFICATION, IAppConfig::VALUE_MIXED, 0);
		$this->insertRow('groups_request_sign', self::GROUPS, IAppConfig::VALUE_MIXED, 0);

		$this->runMigration();
		$this->assertStored('add_footer', self::FOOTER, IAppConfig::VALUE_STRING);
		$this->assertStored('identification_documents', self::IDENTIFICATION, IAppConfig::VALUE_ARRAY);
		$this->assertStored('groups_request_sign', self::GROUPS, IAppConfig::VALUE_STRING);
	}

	public function testLeavesLegacyAndNonJsonValuesUntouched(): void {
		$this->insertRow('add_footer', '1', IAppConfig::VALUE_BOOL, 0);
		$this->insertRow('identification_documents', '"a string, not an object"', IAppConfig::VALUE_BOOL, 0);
		$this->insertRow('groups_request_sign', '{not valid JSON', IAppConfig::VALUE_ARRAY, 0);

		$this->runMigration();
		$this->assertStored('add_footer', '1', IAppConfig::VALUE_BOOL);
		$this->assertStored('identification_documents', '"a string, not an object"', IAppConfig::VALUE_BOOL);
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

	public function testDoesNotChangeAnotherApplicationsConfiguration(): void {
		$appId = 'lsgn_mig_other';
		$original = $this->readRow('add_footer', $appId);
		self::assertNull($original, 'The synthetic test application ID must not be in use.');
		$this->insertRow('add_footer', self::FOOTER, IAppConfig::VALUE_BOOL, 0, $appId);
		try {
			$this->runMigration();
			$this->assertStored('add_footer', self::FOOTER, IAppConfig::VALUE_BOOL, 0, $appId);
		} finally {
			$this->deleteRow('add_footer', $appId);
		}
	}

	public function testSkipsJsonScalarsAndMalformedValues(): void {
		$this->insertRow('add_footer', 'true', IAppConfig::VALUE_BOOL, 0);
		$this->insertRow('identification_documents', 'null', IAppConfig::VALUE_BOOL, 0);
		$this->insertRow('groups_request_sign', '42', IAppConfig::VALUE_ARRAY, 0);
		$this->runMigration();
		$this->assertStored('add_footer', 'true', IAppConfig::VALUE_BOOL);
		$this->assertStored('identification_documents', 'null', IAppConfig::VALUE_BOOL);
		$this->assertStored('groups_request_sign', '42', IAppConfig::VALUE_ARRAY);
	}

	private function runMigration(): void {
		(new Version19001Date20261022200000($this->connection, $this->appConfig))
			->postSchemaChange(new NullOutput(), static fn () => null, []);
	}

	private function assertStored(string $key, string $raw, int $type, int $lazy = 0, string $appId = Application::APP_ID): void {
		$row = $this->readRow($key, $appId);
		self::assertNotNull($row);
		self::assertSame($raw, $row['configvalue']);
		self::assertSame($type, (int)$row['type']);
		self::assertSame($lazy, (int)$row['lazy']);
	}

	/** @return array<string, mixed>|null */
	private function readRow(string $key, string $appId = Application::APP_ID): ?array {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('configvalue', 'type', 'lazy')
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter($appId)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)));
		$row = $qb->executeQuery()->fetchAssociative();
		return $row === false ? null : $row;
	}

	private function deleteRow(string $key, string $appId = Application::APP_ID): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter($appId)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)))
			->executeStatement();
	}

	private function insertRow(string $key, string $raw, int $type, int $lazy, string $appId = Application::APP_ID): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('appconfig')
			->values([
				'appid' => $qb->createNamedParameter($appId),
				'configkey' => $qb->createNamedParameter($key),
				'configvalue' => $qb->createNamedParameter($raw),
				'type' => $qb->createNamedParameter($type),
				'lazy' => $qb->createNamedParameter($lazy),
			])
			->executeStatement();
	}
}
