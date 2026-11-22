<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing;

use OCA\Libresign\Service\Signing\ExternalIntegrationAdministration;
use OCA\Libresign\Service\Signing\ExternalIntegrationRecord;
use OCA\Libresign\Service\Signing\ExternalIntegrationStore;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;

/** @group DB */
final class ExternalIntegrationAdministrationTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private IDBConnection $db;
	private ExternalIntegrationAdministration $administration;
	/** @var list<string> */
	private array $created = [];

	public function setUp(): void {
		parent::setUp();
		$this->db = Server::get(IDBConnection::class);
		$this->administration = Server::get(ExternalIntegrationAdministration::class);
	}

	public function tearDown(): void {
		try {
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

	/** @return array<string, mixed> */
	private static function driver(bool $available = true): array {
		return [
			'id' => 'csc-v1',
			'available' => $available,
			'fields' => [
				['key' => 'baseUrl', 'type' => 'url', 'required' => true, 'secret' => false,
					'validation' => ['schemes' => ['https'], 'allowUserInfo' => false]],
				['key' => 'accessToken', 'type' => 'password', 'required' => false, 'secret' => true],
			],
		];
	}

	/** @param array<string, mixed> $configuration
	 * @param array<string, string> $secrets
	 */
	private function create(array $configuration, #[\SensitiveParameter] array $secrets = []): ExternalIntegrationRecord {
		$record = $this->administration->create(self::driver(), 'Synthetic CSC', $configuration, $secrets);
		$this->created[] = $record->id;
		return $record;
	}

	private function ciphertext(string $publicId): string {
		$qb = $this->db->getQueryBuilder();
		return (string)$qb->select('secrets_enc')->from('libresign_signing_integration')
			->where($qb->expr()->eq('public_id', $qb->createNamedParameter($publicId)))
			->executeQuery()->fetchOne();
	}

	public function testMultipleInstancesAndRedactedFieldStatus(): void {
		$first = $this->create(['baseUrl' => 'https://service-one.example.org'], ['accessToken' => 'synthetic-token-one']);
		$second = $this->create(['baseUrl' => 'https://service-two.example.org']);
		$this->assertNotSame($first->id, $second->id);
		$this->assertSame('csc-v1', $first->driverId);
		$this->assertFalse($first->enabled);
		$firstView = $this->administration->describe(self::driver(), $first->id);
		$secondView = $this->administration->describe(self::driver(), $second->id);
		$this->assertSame([['key' => 'accessToken', 'isConfigured' => true]], $firstView['secretFields']);
		$this->assertSame([['key' => 'accessToken', 'isConfigured' => false]], $secondView['secretFields']);
		$this->assertTrue($firstView['driverAvailable']);
		$this->assertStringNotContainsString('synthetic-token-one', json_encode($firstView, JSON_THROW_ON_ERROR));
		$this->assertArrayNotHasKey('secrets', $firstView);
	}

	public function testInvalidPublicFieldAndSecretPlacementAreRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->create(['baseUrl' => 'https://example.org', 'accessToken' => 'must-not-be-public']);
	}

	public function testUpdatePreservesOmittedCredentialAndAllowsReplacementAndClear(): void {
		$created = $this->create(['baseUrl' => 'https://example.org'], ['accessToken' => 'old-synthetic-credential']);
		$original = $this->ciphertext($created->id);
		$renamed = $this->administration->update(self::driver(), $created->id, $created->revision, 'Renamed CSC');
		$this->assertSame('Renamed CSC', $renamed->name);
		$this->assertSame($original, $this->ciphertext($created->id));
		$replaced = $this->administration->update(
			self::driver(), $created->id, $renamed->revision, null, null, ['accessToken' => 'new-synthetic-credential'],
		);
		$this->assertNotSame($original, $this->ciphertext($created->id));
		$this->assertStringNotContainsString('new-synthetic-credential', json_encode($replaced, JSON_THROW_ON_ERROR));
		$cleared = $this->administration->update(
			self::driver(), $created->id, $replaced->revision, null, null, ['accessToken' => null],
		);
		$this->assertFalse($cleared->hasSecrets);
		$this->assertSame('', $this->ciphertext($created->id));
		$this->assertSame(
			[['key' => 'accessToken', 'isConfigured' => false]],
			$this->administration->describe(self::driver(), $created->id)['secretFields'],
		);
	}

	public function testUnavailableDriverCannotBeEnabled(): void {
		$created = $this->create(['baseUrl' => 'https://example.org']);
		try {
			$this->administration->setEnabled(self::driver(false), $created->id, $created->revision, true);
			$this->fail('Unavailable driver must not be enabled');
		} catch (\DomainException) {
			$this->assertFalse($this->administration->get($created->id)->enabled);
			$this->assertSame($created->revision, $this->administration->get($created->id)->revision);
		}
	}

	public function testDriverIdCannotBeChangedAfterCreation(): void {
		$created = $this->create(['baseUrl' => 'https://example.org']);
		$wrong = self::driver();
		$wrong['id'] = 'other-driver';
		$this->expectException(\InvalidArgumentException::class);
		$this->administration->update($wrong, $created->id, $created->revision, 'Wrong driver');
	}

	public function testRequiredSecretCannotBeInventedWhenExistingRecordHasNone(): void {
		$created = $this->create(['baseUrl' => 'https://example.org']);
		$required = self::driver();
		$required['fields'][1]['required'] = true;
		$this->expectException(\InvalidArgumentException::class);
		$this->administration->update($required, $created->id, $created->revision, 'Still missing');
	}

	/** @return array<string, array{array<string, mixed>}> */
	public static function malformedDriverMetadata(): array {
		$valid = self::driver();
		return [
			'missing driver ID' => [['fields' => $valid['fields']]],
			'numeric driver ID' => [['id' => 42, 'fields' => $valid['fields']]],
			'uppercase driver ID' => [['id' => 'CSC-v1', 'fields' => $valid['fields']]],
			'blank driver ID' => [['id' => '', 'fields' => $valid['fields']]],
			'overlong driver ID' => [['id' => 'c' . str_repeat('a', 64), 'fields' => $valid['fields']]],
			'missing fields' => [['id' => 'csc-v1']],
			'non-array fields' => [['id' => 'csc-v1', 'fields' => 'wrong']],
			'non-list field map' => [['id' => 'csc-v1', 'fields' => ['baseUrl' => $valid['fields'][0]]]],
		];
	}

	#[DataProvider('malformedDriverMetadata')]
	public function testInvalidDriverMetadataIsRejectedBeforePersistence(array $driver): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->administration->create(
			$driver,
			'Synthetic CSC',
			['baseUrl' => 'https://example.org'],
		);
	}

	public function testEnableDisableAndStaleLifecycleRevision(): void {
		$created = $this->create(['baseUrl' => 'https://example.org']);
		$this->assertFalse($created->enabled);
		$enabled = $this->administration->setEnabled(
			self::driver(), $created->id, $created->revision, true,
		);
		$this->assertTrue($enabled->enabled);
		$this->assertSame($created->revision + 1, $enabled->revision);
		$disabled = $this->administration->setEnabled(
			self::driver(), $created->id, $enabled->revision, false,
		);
		$this->assertFalse($disabled->enabled);
		$this->assertSame($enabled->revision + 1, $disabled->revision);
		try {
			$this->administration->setEnabled(
				self::driver(), $created->id, $enabled->revision, true,
			);
			$this->fail('Stale revision must not re-enable a disabled integration');
		} catch (\RuntimeException) {
			$stored = $this->administration->get($created->id);
			$this->assertFalse($stored->enabled);
			$this->assertSame($disabled->revision, $stored->revision);
		}
	}

	public function testUnavailableDriverCanStillBeDisabled(): void {
		$created = $this->create(['baseUrl' => 'https://example.org']);
		$enabled = $this->administration->setEnabled(
			self::driver(), $created->id, $created->revision, true,
		);
		$disabled = $this->administration->setEnabled(
			self::driver(false), $created->id, $enabled->revision, false,
		);
		$this->assertFalse($disabled->enabled);
	}

	public function testSoftDeletionRemovesCredentialsAndDisablesFutureAccess(): void {
		$record = $this->create(
			['baseUrl' => 'https://example.org'],
			['accessToken' => 'synthetic-delete-secret'],
		);
		$enabled = $this->administration->setEnabled(
			self::driver(), $record->id, $record->revision, true,
		);
		$this->assertNotSame('', $this->ciphertext($record->id));
		$this->administration->delete($record->id, $enabled->revision);
		$this->assertSame('', $this->ciphertext($record->id));

		$retained = Server::get(ExternalIntegrationStore::class)->findRetainedReference($record->id);
		$this->assertNotNull($retained);
		$this->assertSame($record->id, $retained->id);
		$this->assertFalse($retained->enabled);
		$this->assertFalse($retained->hasSecrets);
		$this->assertSame([], $retained->configuration);

		$this->expectException(\OutOfBoundsException::class);
		$this->administration->get($record->id);
	}

	public function testStaleUpdatePreservesCurrentNameRevisionAndCiphertext(): void {
		$created = $this->create(
			['baseUrl' => 'https://example.org'],
			['accessToken' => 'unchanged-synthetic-credential'],
		);
		$updated = $this->administration->update(
			self::driver(), $created->id, $created->revision, 'First update',
		);
		$ciphertext = $this->ciphertext($created->id);
		try {
			$this->administration->update(
				self::driver(),
				$created->id,
				$created->revision,
				'Stale update',
				null,
				['accessToken' => 'should-not-be-persisted'],
			);
			$this->fail('Stale update must not replace the newer state');
		} catch (\RuntimeException) {
			$stored = $this->administration->get($created->id);
			$this->assertSame('First update', $stored->name);
			$this->assertSame($updated->revision, $stored->revision);
			$this->assertSame($ciphertext, $this->ciphertext($created->id));
		}
	}

	public function testRequiredSecretRemainsConfiguredWhenOmitted(): void {
		$created = $this->create(
			['baseUrl' => 'https://example.org'],
			['accessToken' => 'synthetic-required-secret'],
		);
		$driver = self::driver();
		$driver['fields'][1]['required'] = true;
		$ciphertext = $this->ciphertext($created->id);
		$updated = $this->administration->update(
			$driver, $created->id, $created->revision, 'Updated without replacement',
		);
		$this->assertSame($ciphertext, $this->ciphertext($created->id));
		$this->assertSame(
			[['key' => 'accessToken', 'isConfigured' => true]],
			$this->administration->describe($driver, $created->id)['secretFields'],
		);
		try {
			$this->administration->update(
				$driver,
				$created->id,
				$updated->revision,
				null,
				null,
				['accessToken' => null],
			);
			$this->fail('Required secret must not be cleared');
		} catch (\InvalidArgumentException) {
			$this->assertSame($updated->revision, $this->administration->get($created->id)->revision);
			$this->assertSame($ciphertext, $this->ciphertext($created->id));
		}
	}

	public function testInvalidSecretPatchLeavesRecordUnchanged(): void {
		$created = $this->create(
			['baseUrl' => 'https://example.org'],
			['accessToken' => 'synthetic-credential'],
		);
		$ciphertext = $this->ciphertext($created->id);
		try {
			$this->administration->update(
				self::driver(),
				$created->id,
				$created->revision,
				'Unauthorized change',
				null,
				['baseUrl' => 'secret-in-wrong-place'],
			);
			$this->fail('Unknown secret field must be rejected');
		} catch (\InvalidArgumentException) {
			$stored = $this->administration->get($created->id);
			$this->assertSame($created->name, $stored->name);
			$this->assertSame($created->revision, $stored->revision);
			$this->assertSame($ciphertext, $this->ciphertext($created->id));
		}
	}

	public function testDescriptionRejectsMismatchedDriverWithoutRevealingSecrets(): void {
		$created = $this->create(
			['baseUrl' => 'https://example.org'],
			['accessToken' => 'synthetic-credential'],
		);
		$wrongDriver = self::driver();
		$wrongDriver['id'] = 'different-driver';
		$this->expectException(\InvalidArgumentException::class);
		$this->administration->describe($wrongDriver, $created->id);
	}

}
