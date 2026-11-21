<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing;

use OCA\Libresign\Service\Signing\ExternalIntegrationStore;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Server;

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

	private function create(string $name = 'Synthetic CSC') : \OCA\Libresign\Service\Signing\ExternalIntegrationRecord {
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
}
