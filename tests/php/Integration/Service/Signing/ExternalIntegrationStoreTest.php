<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing;

use OCA\Libresign\Service\Signing\ExternalIntegrationStore;
use OCA\Libresign\Service\Signing\ExternalIntegrationSecrets;
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
		// Test-created IDs only. Never clear the integration table globally.
		foreach ($this->created as $publicId) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('libresign_signing_integration')
				->where($qb->expr()->eq('public_id', $qb->createNamedParameter($publicId)))
				->executeStatement();
		}
		parent::tearDown();
	}

	private function create(string $name = 'Synthetic CSC'): \OCA\Libresign\Service\Signing\ExternalIntegrationRecord {
		$record = $this->store->create('csc', $name, ['serviceUrl' => 'https://example.invalid'], ['accessToken' => 'integration-test-secret']);
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

	public function testPersistedSecretIsEncryptedAndRedactedFromRead(): void {
		$record = $this->create();
		$stored = $this->encryptedValue($record->id);
		$this->assertNotSame('', $stored);
		$this->assertStringNotContainsString('integration-test-secret', $stored);
		$this->assertTrue($this->store->get($record->id)->hasSecrets);
		$this->assertStringNotContainsString('integration-test-secret', json_encode($this->store->get($record->id), JSON_THROW_ON_ERROR));
	}

	public function testOmittingSecretChangesDoesNotModifyCiphertext(): void {
		$record = $this->create();
		$stored = $this->encryptedValue($record->id);
		$updated = $this->store->update($record->id, $record->revision, 'Updated name', ['serviceUrl' => 'https://example.invalid']);
		$this->assertSame('Updated name', $updated->name);
		$this->assertSame($stored, $this->encryptedValue($record->id));
	}

	public function testSoftDeletePreservesReferenceAndEncryptedSecret(): void {
		$record = $this->create();
		$enabled = $this->store->setEnabled($record->id, $record->revision, true);
		$this->assertTrue($enabled->enabled);
		$encrypted = $this->encryptedValue($record->id);
		$this->store->softDelete($enabled->id, $enabled->revision);
		$this->assertNull($this->store->find($enabled->id));
		$this->assertFalse($this->store->findRetainedReference($enabled->id)->enabled);
		$this->assertSame($encrypted, $this->encryptedValue($enabled->id));
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
		$record = $this->store->create('csc', 'No credentials yet', ['serviceUrl' => 'https://example.invalid'], []);
		$this->created[] = $record->id;
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

	public function testEnableDisableAndRetainedDeletionDoNotLoseIdentityOrSecrets(): void {
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
		$this->assertSame($initialCiphertext, $this->encryptedValue($record->id));
		$this->expectException(\RuntimeException::class);
		$this->store->setEnabled($record->id, $retained->revision, true);
	}

	public function testStaleSecretUpdateCannotOverwriteNewerState(): void {
		$record = $this->create();
		$originalCiphertext = $this->encryptedValue($record->id);
		$enabled = $this->store->setEnabled($record->id, $record->revision, true);
		try {
			$this->store->update($record->id, $record->revision, 'Stale name', $record->configuration, ['accessToken' => 'stale-secret']);
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
		$this->store->create($driverId, 'CSC', [], []);
	}

	/** @return array<string, array{string}> */
	public static function invalidNames(): array {
		return [
			'blank name' => [''],
			'whitespace-only name' => ['   '],
			'name over 128 bytes' => [str_repeat('x', 129)],
		];
	}

	#[DataProvider('invalidNames')]
	public function testInvalidDisplayNameIsRejectedOnCreate(string $name): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->store->create('csc', $name, [], []);
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
		$record = $this->store->create('csc', '  Company CSC  ', [], []);
		$this->created[] = $record->id;
		$this->assertSame('Company CSC', $record->name);
		$changed = $this->store->update($record->id, $record->revision, '  Renamed CSC  ', []);
		$this->assertSame('Renamed CSC', $changed->name);
	}

	public function testOversizedConfigurationCannotBeSaved(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->store->create('csc', 'CSC', ['url' => str_repeat('x', 32768)], []);
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
			$created = $this->store->create('csc', 'Synthetic integration ' . $n, [], []);
			$this->created[] = $created->id;
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
}
