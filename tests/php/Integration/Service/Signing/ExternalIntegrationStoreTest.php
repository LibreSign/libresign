<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing;

use OCA\Libresign\Service\Signing\ExternalIntegrationSecrets;
use OCA\Libresign\Service\Signing\ExternalIntegrationStore;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;

/** @group DB */
final class ExternalIntegrationStoreTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private IDBConnection $db;
	private ExternalIntegrationStore $store;
	/** @var list<string> */
	private array $created = [];

	public function setUp(): void {
		parent::setUp();
		$this->db = Server::get(IDBConnection::class);
		$this->store = Server::get(ExternalIntegrationStore::class);
	}

	public function tearDown(): void {
		try {
			// Test-created IDs only. Never clear the integration table globally.
			foreach ($this->created as $publicId) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete('libresign_signing_integration')
					->where($qb->expr()->eq('public_id', $qb->createNamedParameter($publicId)))
					->executeStatement();
			}
		} finally {
			parent::tearDown();
		}
	}

	private function create(string $name = 'Synthetic CSC'): \OCA\Libresign\Service\Signing\ExternalIntegrationRecord {
		return $this->createTracked(
			'csc',
			$name,
			['serviceUrl' => 'https://example.invalid'],
			['accessToken' => 'integration-test-secret'],
		);
	}

	/**
	 * @param array<string, mixed> $configuration
	 * @param array<string, string> $secrets
	 */
	private function createTracked(
		string $driverId,
		string $name,
		array $configuration,
		#[\SensitiveParameter] array $secrets,
		string $scopeType = 'system',
		string $scopeId = '',
	): \OCA\Libresign\Service\Signing\ExternalIntegrationRecord {
		$record = $this->store->create($driverId, $name, $configuration, $secrets, $scopeType, $scopeId);
		$this->created[] = $record->id;
		return $record;
	}

	private function encryptedValue(string $publicId): string {
		$qb = $this->db->getQueryBuilder();
		$value = $qb->select('secrets_enc')->from('libresign_signing_integration')
			->where($qb->expr()->eq('public_id', $qb->createNamedParameter($publicId)))
			->executeQuery()->fetchOne();
		return (string)$value;
	}

	public function testTwoInstancesOfSameDriverHaveDistinctStableIds(): void {
		$one = $this->create('First CSC');
		$two = $this->create('Second CSC');
		$this->assertNotSame($one->id, $two->id);
		$this->assertSame('csc', $one->driverId);
		$this->assertSame('csc', $two->driverId);
		$this->assertFalse($one->enabled);
		$this->assertFalse($two->enabled);
		$this->assertSame($one->id, $this->store->get($one->id)->id);
	}

	public function testScopedIntegrationsArePersistedAndPaginatedWithoutCrossClientLeakage(): void {
		$system = $this->createTracked('csc', 'Global', [], [], 'system', '');
		$alpha = $this->createTracked('csc', 'Client A', [], [], 'group', 'client-a');
		$beta = $this->createTracked('csc', 'Client B', [], [], 'group', 'client-b');
		$personal = $this->createTracked('csc', 'Personal', [], [], 'user', 'alice');

		$this->assertSame('group', $this->store->get($alpha->id)->scopeType);
		$this->assertSame('client-a', $this->store->get($alpha->id)->scopeId);
		$this->assertSame(['scopeType' => 'group', 'scopeId' => 'client-a'],
			array_intersect_key($this->store->get($alpha->id)->jsonSerialize(), ['scopeType' => true, 'scopeId' => true]));
		$this->assertSame([$alpha->id], array_column($this->store->listPage(0, 50, 'group', 'client-a')['items'], 'id'));
		$this->assertSame([$beta->id], array_column($this->store->listPage(0, 50, 'group', 'client-b')['items'], 'id'));
		$this->assertSame([$personal->id], array_column($this->store->listPage(0, 50, 'user', 'alice')['items'], 'id'));
		$this->assertContains($system->id, array_column($this->store->listPage(0, 50, 'system', '')['items'], 'id'));
	}

	public function testPersistedSecretIsEncryptedAndRedactedFromRead(): void {
		$record = $this->create();
		$stored = $this->encryptedValue($record->id);
		$this->assertNotSame('', $stored);
		$this->assertStringNotContainsString('integration-test-secret', $stored);
		$this->assertTrue($this->store->get($record->id)->hasSecrets);
		$this->assertStringNotContainsString(
			'integration-test-secret',
			json_encode($this->store->get($record->id), JSON_THROW_ON_ERROR),
		);
		$qb = $this->db->getQueryBuilder();
		$internalId = (int)$qb->select('id')->from('libresign_signing_integration')
			->where($qb->expr()->eq('public_id', $qb->createNamedParameter($record->id)))
			->executeQuery()->fetchOne();
		$page = $this->store->listPage($internalId - 1, 1);
		$this->assertCount(1, $page['items']);
		$this->assertSame($record->id, $page['items'][0]->id);
		$this->assertStringNotContainsString($stored, json_encode($page['items'][0], JSON_THROW_ON_ERROR));
		$this->assertStringNotContainsString('integration-test-secret', json_encode($page['items'][0], JSON_THROW_ON_ERROR));
	}

	public function testOmittingSecretChangesDoesNotModifyCiphertext(): void {
		$record = $this->create();
		$stored = $this->encryptedValue($record->id);
		$updated = $this->store->update(
			$record->id,
			$record->revision,
			'Updated name',
			['serviceUrl' => 'https://example.invalid'],
		);
		$this->assertSame('Updated name', $updated->name);
		$this->assertSame($stored, $this->encryptedValue($record->id));
	}

	public function testSoftDeleteRetainsIdentityButPurgesCredentials(): void {
		$record = $this->create();
		$enabled = $this->store->setEnabled($record->id, $record->revision, true);
		$this->assertTrue($enabled->enabled);
		$encrypted = $this->encryptedValue($record->id);
		$this->store->softDelete($enabled->id, $enabled->revision);
		$this->assertNull($this->store->find($enabled->id));
		$retained = $this->store->findRetainedReference($enabled->id);
		$this->assertNotNull($retained);
		$this->assertFalse($retained->enabled);
		$this->assertFalse($retained->hasSecrets);
		$this->assertSame([], $retained->configuration);
		$this->assertNotSame('', $encrypted);
		$this->assertSame('', $this->encryptedValue($enabled->id));
	}

	public function testUpdateRejectsStaleRevisionWithoutClobberingState(): void {
		$record = $this->create();
		$updated = $this->store->setEnabled($record->id, $record->revision, true);
		$this->expectException(\RuntimeException::class);
		try {
			$this->store->update($record->id, $record->revision, 'Stale write', []);
		} finally {
			$this->assertTrue($this->store->get($updated->id)->enabled);
			$this->assertSame($updated->revision, $this->store->get($updated->id)->revision);
		}
	}

	public function testEmptySecretSetIsPersistedAndNeverExposedAsConfigured(): void {
		$record = $this->createTracked('csc', 'No credentials yet', ['serviceUrl' => 'https://example.invalid'], []);
		$this->assertFalse($record->hasSecrets);
		$this->assertSame('', $this->encryptedValue($record->id));
		$this->assertSame(false, $this->store->find($record->id)?->hasSecrets);
	}

	public function testSecretReplacementAndExplicitClearingAreDurable(): void {
		$record = $this->create();
		$previous = $this->encryptedValue($record->id);
		$updated = $this->store->update(
			$record->id, $record->revision, 'Updated CSC',
			['serviceUrl' => 'https://example.invalid'], ['accessToken' => 'new-synthetic-secret'],
		);
		$this->assertSame($record->id, $updated->id);
		$this->assertTrue($updated->hasSecrets);
		$this->assertSame($record->revision + 1, $updated->revision);
		$this->assertNotSame($previous, $this->encryptedValue($record->id));
		$codec = Server::get(ExternalIntegrationSecrets::class);
		$this->assertSame(['accessToken' => 'new-synthetic-secret'], $codec->decrypt($this->encryptedValue($record->id)));
		$this->assertStringNotContainsString('new-synthetic-secret', json_encode($updated, JSON_THROW_ON_ERROR));

		$cleared = $this->store->update(
			$updated->id, $updated->revision, $updated->name,
			$updated->configuration, ['accessToken' => null],
		);
		$this->assertFalse($cleared->hasSecrets);
		$this->assertSame('', $this->encryptedValue($record->id));
	}

	public function testEnableDisablePreservesSecretsUntilDeletionPurgesThem(): void {
		$record = $this->create();
		$initialCiphertext = $this->encryptedValue($record->id);
		$enabled = $this->store->setEnabled($record->id, $record->revision, true);
		$this->assertTrue($enabled->enabled);
		$this->assertSame($record->revision + 1, $enabled->revision);
		$disabled = $this->store->setEnabled($record->id, $enabled->revision, false);
		$this->assertFalse($disabled->enabled);
		$this->assertSame($initialCiphertext, $this->encryptedValue($record->id));
		$this->store->softDelete($record->id, $disabled->revision);
		$this->assertNull($this->store->find($record->id));
		$retained = $this->store->findRetainedReference($record->id);
		$this->assertNotNull($retained);
		$this->assertSame($record->id, $retained->id);
		$this->assertFalse($retained->enabled);
		$this->assertFalse($retained->hasSecrets);
		$this->assertSame([], $retained->configuration);
		$this->assertSame('', $this->encryptedValue($record->id));
		$this->expectException(\RuntimeException::class);
		$this->store->setEnabled($record->id, $retained->revision, true);
	}

	public function testStaleSecretUpdateCannotOverwriteNewerState(): void {
		$record = $this->create();
		$originalCiphertext = $this->encryptedValue($record->id);
		$enabled = $this->store->setEnabled($record->id, $record->revision, true);
		try {
			$this->store->update(
				$record->id,
				$record->revision,
				'Stale name',
				$record->configuration,
				['accessToken' => 'stale-secret'],
			);
			$this->fail('A stale secret replacement must not be applied');
		} catch (\RuntimeException $e) {
			$this->assertSame('Integration changed concurrently', $e->getMessage());
		}
		$this->assertSame($originalCiphertext, $this->encryptedValue($record->id));
		$this->assertSame($enabled->revision, $this->store->get($record->id)->revision);
	}

	/** @return array<string, array{string}> */
	public static function invalidDriverIds(): array {
		return [
			'blank driver ID' => [''],
			'uppercase driver ID' => ['CSC'],
			'leading digit' => ['2csc'],
			'space in ID' => ['csc instance'],
			'overlong ID' => [str_repeat('d', 65)],
		];
	}

	#[DataProvider('invalidDriverIds')]
	public function testInvalidDriverIdIsRejected(string $driverId): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->createTracked($driverId, 'CSC', [], []);
	}

	/** @return array<string, array{string}> */
	public static function invalidNames(): array {
		return [
			'blank name' => [''],
			'whitespace-only name' => ['   '],
			'name over 128 characters' => [str_repeat('x', 129)],
			'multibyte name over 128 characters' => [str_repeat('Á', 129)],
			'invalid UTF-8 name' => ["\xC3\x28"],
		];
	}

	#[DataProvider('invalidNames')]
	public function testInvalidDisplayNameIsRejectedOnCreate(string $name): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->createTracked('csc', $name, [], []);
	}

	#[DataProvider('invalidNames')]
	public function testInvalidDisplayNameIsRejectedOnUpdate(string $name): void {
		$record = $this->create();
		try {
			$this->store->update($record->id, $record->revision, $name, $record->configuration);
			$this->fail('Invalid names must be rejected during updates');
		} catch (\InvalidArgumentException) {
			$this->assertSame($record->name, $this->store->get($record->id)->name);
			$this->assertSame($record->revision, $this->store->get($record->id)->revision);
		}
	}

	public function testNameIsNormalizedOnCreateAndUpdate(): void {
		$record = $this->createTracked('csc', '  Company CSC  ', [], []);
		$this->assertSame('Company CSC', $record->name);
		$changed = $this->store->update($record->id, $record->revision, '  Renamed CSC  ', []);
		$this->assertSame('Renamed CSC', $changed->name);
	}

	public function testOversizedConfigurationCannotBeSaved(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->createTracked('csc', 'CSC', ['url' => str_repeat('x', 32768)], []);
	}

	/** @return array<string, array{int, int}> */
	public static function invalidPages(): array {
		return [
			'negative cursor' => [-1, 50],
			'zero page size' => [0, 0],
			'oversized page request' => [0, 201],
		];
	}

	#[DataProvider('invalidPages')]
	public function testInvalidPaginationIsRejected(int $cursor, int $size): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->store->listPage($cursor, $size);
	}

	public function testKeysetPaginationDoesNotSilentlyTruncateAtOneHundred(): void {
		$qb = $this->db->getQueryBuilder();
		$lastId = (int)$qb->select($qb->func()->max('id'))->from('libresign_signing_integration')->executeQuery()->fetchOne();
		$expected = [];
		for ($n = 0; $n < 101; $n++) {
			$created = $this->createTracked('csc', 'Synthetic integration ' . $n, [], []);
			$expected[] = $created->id;
		}
		$seen = [];
		$cursor = $lastId;
		$pages = 0;
		do {
			$page = $this->store->listPage($cursor, 37);
			$this->assertLessThanOrEqual(37, count($page['items']));
			foreach ($page['items'] as $item) {
				$seen[] = $item->id;
			}
			$pages++;
			$cursor = $page['nextCursor'] ?? 0;
		} while ($page['nextCursor'] !== null);
		$this->assertSame(3, $pages);
		$this->assertSame($expected, $seen);
	}

	public function testListOmitsDeletedIntegrationsWithoutDeletingExistingReferences(): void {
		$qb = $this->db->getQueryBuilder();
		$lastId = (int)$qb->select($qb->func()->max('id'))->from('libresign_signing_integration')->executeQuery()->fetchOne();
		$first = $this->create('Retained CSC');
		$second = $this->create('Active CSC');
		$this->store->softDelete($first->id, $first->revision);
		$page = $this->store->listPage($lastId, 20);
		$this->assertSame([$second->id], array_map(static fn ($item): string => $item->id, $page['items']));
		$this->assertNotNull($this->store->findRetainedReference($first->id));
	}

	public function testMalformedPublicIdIsNotAValidIntegration(): void {
		$this->assertNull($this->store->find('not-an-id'));
		$this->assertNull($this->store->findRetainedReference('not-an-id'));
		$this->expectException(\OutOfBoundsException::class);
		$this->store->get('not-an-id');
	}

	/** @return array<string, array{string}> */
	public static function validInternationalNames(): array {
		return [
			'accented Latin characters' => [str_repeat('Á', 65)],
			'exactly 128 multibyte characters' => [str_repeat('签', 128)],
		];
	}

	#[DataProvider('validInternationalNames')]
	public function testValidInternationalNameIsBoundedByCharacters(string $name): void {
		$record = $this->createTracked('csc', $name, [], []);
		$this->assertSame($name, $record->name);
		$this->assertSame($name, $this->store->get($record->id)->name);
	}

	public function testEmptyConfigurationIsPersistedAsJsonObject(): void {
		$record = $this->createTracked('csc', 'No settings', [], []);
		$qb = $this->db->getQueryBuilder();
		$stored = $qb->select('configuration')->from('libresign_signing_integration')
			->where($qb->expr()->eq('public_id', $qb->createNamedParameter($record->id)))
			->executeQuery()->fetchOne();
		$this->assertSame('{}', $stored);
		$this->assertSame('{}', json_encode($record->jsonSerialize()['configuration'], JSON_THROW_ON_ERROR));
	}

	/** @return array<string, array{array<mixed, mixed>}> */
	public static function invalidConfigurations(): array {
		return [
			'list input' => [[['url' => 'https://example.invalid']]],
			'numeric root key' => [[2 => 'unexpected']],
			'empty root key' => [['' => 'unexpected']],
			'invalid UTF-8 content' => [['field' => "\xC3\x28"]],
		];
	}

	#[DataProvider('invalidConfigurations')]
	public function testInvalidConfigurationCannotBePersisted(array $configuration): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->createTracked('csc', 'Invalid config', $configuration, []);
	}

	/** @return array<string, array{string}> */
	public static function malformedPersistedConfigurations(): array {
		return [
			'JSON array' => ['[]'],
			'JSON scalar' => ['null'],
			'JSON syntax error' => ['malformed-json'],
			'numeric field in JSON object' => ['{"0":"untrusted"}'],
			'blank field in JSON object' => ['{"":"untrusted"}'],
		];
	}

	#[DataProvider('malformedPersistedConfigurations')]
	public function testMalformedPersistedConfigurationIsRejected(string $invalidJson): void {
		$record = $this->create();
		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_signing_integration')
			->set('configuration', $qb->createNamedParameter($invalidJson))
			->where($qb->expr()->eq('public_id', $qb->createNamedParameter($record->id)))
			->executeStatement();
		$this->expectException(\UnexpectedValueException::class);
		$this->store->get($record->id);
	}

	/** @return array<string, array{string}> */
	public static function staleMutationTypes(): array {
		return [
			'update the name' => ['rename'],
			'reenable' => ['enable'],
			'disable' => ['disable'],
			'delete' => ['delete'],
		];
	}

	#[DataProvider('staleMutationTypes')]
	public function testStaleLifecycleMutationsDoNotChangeTheCurrentRecord(string $mutation): void {
		$initial = $this->create();
		$current = $this->store->setEnabled($initial->id, $initial->revision, true);
		$ciphertext = $this->encryptedValue($current->id);
		try {
			match ($mutation) {
				'rename' => $this->store->update($initial->id, $initial->revision, 'stale update', $initial->configuration),
				'enable' => $this->store->setEnabled($initial->id, $initial->revision, true),
				'disable' => $this->store->setEnabled($initial->id, $initial->revision, false),
				'delete' => $this->store->softDelete($initial->id, $initial->revision),
			};
			$this->fail('Stale mutation must not succeed');
		} catch (\RuntimeException $e) {
			$this->assertSame('Integration changed concurrently', $e->getMessage());
		}
		$after = $this->store->get($initial->id);
		$this->assertTrue($after->enabled);
		$this->assertSame($current->revision, $after->revision);
		$this->assertSame($current->name, $after->name);
		$this->assertSame($ciphertext, $this->encryptedValue($initial->id));
	}
}
