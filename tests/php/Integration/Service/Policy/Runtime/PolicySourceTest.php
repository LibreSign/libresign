<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Policy\Runtime;

use OC\AppConfig as RealAppConfig;
use OC\Config\ConfigManager;
use OC\Config\PresetManager;
use OC\Memcache\Factory as CacheFactory;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyValue;
use OCA\Libresign\Service\Policy\Provider\Signature\SignatureFlowPolicy;
use OCA\Libresign\Service\Policy\Runtime\PolicySource;
use OCA\Libresign\Tests\lib\RecordingLogger;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Security\ICrypto;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Regression coverage for #9456: resolving a policy whose AppConfig key was
 * persisted under a previous policy's type (a legacy scalar instead of the
 * current JSON/array shape) must not make Nextcloud's real AppConfig log
 * "Config value ... is stored as ... but was requested as ...", and must not
 * force an unrelated lazy AppConfig value to load either.
 *
 * Uses the real OC\AppConfig (not the in-memory AppConfigOverwrite test
 * double other LibreSign tests rely on) because only the real implementation
 * reproduces Nextcloud's own type-conflict logging.
 */
#[Group('DB')]
final class PolicySourceTest extends \Test\TestCase {
	private const OVERRIDE_SUFFIX = '.allow_child_override';
	private const LAZY_PROBE_KEY = 'ls9456_lazy_probe';

	private IDBConnection $connection;
	private RealAppConfig $realAppConfig;
	private RecordingLogger $logger;

	/** @var array<string, array<string, mixed>|null> */
	private array $originalRows = [];

	public function setUp(): void {
		parent::setUp();
		$this->connection = Server::get(IDBConnection::class);
		$this->logger = new RecordingLogger();
		$this->realAppConfig = new RealAppConfig(
			$this->connection,
			Server::get(IConfig::class),
			Server::get(ConfigManager::class),
			Server::get(PresetManager::class),
			$this->logger,
			Server::get(ICrypto::class),
			Server::get(CacheFactory::class),
		);
		$this->overwriteService(IAppConfig::class, $this->realAppConfig);

		foreach ($this->allKeys() as $key) {
			$this->originalRows[$key] = $this->readRow($key);
			$this->deleteRow($key);
		}
		$this->realAppConfig->clearCache();
		$this->logger->reset();
	}

	public function tearDown(): void {
		try {
			foreach ($this->originalRows as $key => $row) {
				$this->deleteRow($key);
				if ($row !== null) {
					$this->insertRow($key, (string)$row['configvalue'], (int)$row['type'], (int)$row['lazy']);
				}
			}
			$this->realAppConfig->clearCache();
		} finally {
			parent::tearDown();
		}
	}

	/** @return list<string> */
	private function allKeys(): array {
		$keys = [];
		foreach ([
			FooterPolicy::SYSTEM_APP_CONFIG_KEY,
			IdentificationDocumentsPolicy::SYSTEM_APP_CONFIG_KEY,
			RequestSignGroupsPolicy::SYSTEM_APP_CONFIG_KEY,
			SignatureFlowPolicy::SYSTEM_APP_CONFIG_KEY,
		] as $key) {
			$keys[] = $key;
			$keys[] = $key . self::OVERRIDE_SUFFIX;
		}
		return [...$keys, self::LAZY_PROBE_KEY];
	}

	#[DataProvider('provideLegacyPolicyValues')]
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testLoadSystemPolicyDoesNotLogTypeConflictWarning(
		string $policyKey,
		string $configKey,
		string $storedValue,
		int $storedType,
		mixed $expectedValue,
	): void {
		$this->insertRow($configKey, $storedValue, $storedType, 0);
		$this->realAppConfig->clearCache();
		$this->logger->reset();

		$source = Server::get(PolicySource::class);

		$firstLayer = $source->loadSystemPolicy($policyKey);
		$secondLayer = $source->loadSystemPolicy($policyKey);

		self::assertNotNull($firstLayer);
		self::assertSame($expectedValue, self::decodeForComparison($policyKey, $firstLayer->getValue()));
		self::assertSame($firstLayer->getValue(), $secondLayer?->getValue());

		self::assertFalse(
			$this->logger->hasRecordMatching('but was requested as'),
			'Expected no AppConfig type-conflict warning, got: ' . json_encode($this->logger->getRecords()),
		);
	}

	/**
	 * Data providers running with #[RunInSeparateProcess] must stay
	 * serializable, so comparisons are expressed as plain values here and
	 * resolved against the matching PolicyValue decoder in the test itself.
	 */
	private static function decodeForComparison(string $policyKey, mixed $value): mixed {
		return match ($policyKey) {
			RequestSignGroupsPolicy::KEY => RequestSignGroupsPolicyValue::decodePolicy($value),
			FooterPolicy::KEY => FooterPolicyValue::normalize($value)['enabled'],
			default => $value,
		};
	}

	public static function provideLegacyPolicyValues(): array {
		return [
			'groups_request_sign stored as a legacy plain array' => [
				RequestSignGroupsPolicy::KEY,
				RequestSignGroupsPolicy::SYSTEM_APP_CONFIG_KEY,
				json_encode(['finance', 'admin']),
				IAppConfig::VALUE_ARRAY,
				['allowGroups' => ['admin', 'finance'], 'denyGroups' => []],
			],
			'identification_documents stored as a legacy boolean true' => [
				IdentificationDocumentsPolicy::KEY,
				IdentificationDocumentsPolicy::SYSTEM_APP_CONFIG_KEY,
				'1',
				IAppConfig::VALUE_BOOL,
				['enabled' => true, 'approvers' => ['admin']],
			],
			'identification_documents stored as a legacy boolean false' => [
				IdentificationDocumentsPolicy::KEY,
				IdentificationDocumentsPolicy::SYSTEM_APP_CONFIG_KEY,
				'0',
				IAppConfig::VALUE_BOOL,
				['enabled' => false, 'approvers' => ['admin']],
			],
			'identification_documents stored as legacy string true' => [
				IdentificationDocumentsPolicy::KEY,
				IdentificationDocumentsPolicy::SYSTEM_APP_CONFIG_KEY,
				'1',
				IAppConfig::VALUE_STRING,
				['enabled' => true, 'approvers' => ['admin']],
			],
			'identification_documents stored as legacy string false' => [
				IdentificationDocumentsPolicy::KEY,
				IdentificationDocumentsPolicy::SYSTEM_APP_CONFIG_KEY,
				'0',
				IAppConfig::VALUE_STRING,
				['enabled' => false, 'approvers' => ['admin']],
			],
			'identification_documents stored as JSON string' => [
				IdentificationDocumentsPolicy::KEY,
				IdentificationDocumentsPolicy::SYSTEM_APP_CONFIG_KEY,
				'{"enabled":true,"approvers":["reviewer"]}',
				IAppConfig::VALUE_STRING,
				['enabled' => true, 'approvers' => ['reviewer']],
			],
			'control: identification_documents stored as its current array type' => [
				IdentificationDocumentsPolicy::KEY,
				IdentificationDocumentsPolicy::SYSTEM_APP_CONFIG_KEY,
				'{"enabled":true,"approvers":["admin"]}',
				IAppConfig::VALUE_ARRAY,
				['enabled' => true, 'approvers' => ['admin']],
			],
			'add_footer stored as a legacy boolean false' => [
				FooterPolicy::KEY,
				FooterPolicy::SYSTEM_APP_CONFIG_KEY,
				'0',
				IAppConfig::VALUE_BOOL,
				false,
			],
			'control: signature_flow stored with its current declared type' => [
				SignatureFlowPolicy::KEY,
				SignatureFlowPolicy::SYSTEM_APP_CONFIG_KEY,
				'ordered_numeric',
				IAppConfig::VALUE_STRING,
				'ordered_numeric',
			],
		];
	}

	/**
	 * Point 2 of the #9456 review: the fix must read the exact stored type
	 * instead of a broad prefix lookup, which in Nextcloud forces all lazy
	 * AppConfig values of the app to load. Reading one non-lazy system
	 * policy must not trigger that unrelated lazy load.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testLoadSystemPolicyDoesNotTriggerUnrelatedLazyAppConfigLoad(): void {
		$this->realAppConfig->setValueString(Application::APP_ID, self::LAZY_PROBE_KEY, 'probe', lazy: true);
		$this->insertRow(SignatureFlowPolicy::SYSTEM_APP_CONFIG_KEY, 'ordered_numeric', IAppConfig::VALUE_STRING, 0);
		$this->realAppConfig->clearCache();
		$this->logger->reset();

		$source = Server::get(PolicySource::class);
		$source->loadSystemPolicy(SignatureFlowPolicy::KEY);

		self::assertFalse(
			$this->logger->hasRecordMatching('The loading of lazy AppConfig values have been triggered'),
			'Reading a non-lazy system policy must not force lazy AppConfig values to load: '
				. json_encode($this->logger->getRecords()),
		);
	}

	/** @return array<string, mixed>|null */
	private function readRow(string $key): ?array {
		$qb = $this->connection->getQueryBuilder();
		$row = $qb->select('configvalue', 'type', 'lazy')
			->from('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)))
			->executeQuery()->fetchAssociative();
		return $row === false ? null : $row;
	}

	private function deleteRow(string $key): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('appconfig')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter(Application::APP_ID)))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter($key)))
			->executeStatement();
	}

	private function insertRow(string $key, string $value, int $type, int $lazy): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('appconfig')->values([
			'appid' => $qb->createNamedParameter(Application::APP_ID),
			'configkey' => $qb->createNamedParameter($key),
			'configvalue' => $qb->createNamedParameter($value),
			'type' => $qb->createNamedParameter($type),
			'lazy' => $qb->createNamedParameter($lazy),
		])->executeStatement();
	}
}
