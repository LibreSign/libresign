<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Migration;

use DateTime;
use OCA\Libresign\Db\Crl;
use OCA\Libresign\Db\CrlMapper;
use OCA\Libresign\Enum\CRLStatus;
use OCA\Libresign\Migration\Version18005Date20260923000000;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Server;

final class Version18005Date20260923000000Test extends TestCase {
	private IDBConnection $connection;
	private CrlMapper $crlMapper;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$connection = Server::get(IDBConnection::class);
		self::assertInstanceOf(IDBConnection::class, $connection);
		$this->connection = $connection;
		$this->crlMapper = Server::get(CrlMapper::class);
		$this->deleteFixtures();
	}

	#[\Override]
	public function tearDown(): void {
		$this->deleteFixtures();
		parent::tearDown();
	}

	public function testRepairsRowWithAllLegacyMetadataMissing(): void {
		$this->insertCertificate('8665-all-missing', CRLStatus::ISSUED, null, null, '');

		$this->runMigration();

		$certificate = $this->crlMapper->findBySerialNumber('8665-all-missing');
		self::assertSame('abc123', $certificate->getInstanceId());
		self::assertSame(1, $certificate->getGeneration());
		self::assertSame('openssl', $certificate->getEngine());
	}

	public function testRepairsGenerationWhenItIsTheOnlyMissingField(): void {
		$this->insertCertificate('8665-generation-missing', CRLStatus::ISSUED, 'abc123', null, 'openssl');

		$this->runMigration();

		$certificate = $this->crlMapper->findBySerialNumber('8665-generation-missing');
		self::assertSame(1, $certificate->getGeneration());
	}

	public function testRepairsRevokedCertificateSoItReturnsToItsCrlScope(): void {
		$this->insertCertificate('8665-revoked-missing', CRLStatus::REVOKED, null, null, '');

		$this->runMigration();

		$certificate = $this->crlMapper->findBySerialNumber('8665-revoked-missing');
		self::assertSame('abc123', $certificate->getInstanceId());
		self::assertSame(1, $certificate->getGeneration());
		self::assertSame('openssl', $certificate->getEngine());

		$revoked = $this->crlMapper->getRevokedCertificates('abc123', 1, 'openssl');
		self::assertContains('8665-revoked-missing', array_map(
			static fn (Crl $entry): string => $entry->getSerialNumber(),
			$revoked,
		));
	}

	public function testDoesNotCreateHybridScopeWhenExistingInstanceIdConflicts(): void {
		$this->insertCertificate('8665-conflicting-instance', CRLStatus::ISSUED, 'different-instance', null, 'openssl');

		$this->runMigration();

		$certificate = $this->crlMapper->findBySerialNumber('8665-conflicting-instance');
		self::assertSame('different-instance', $certificate->getInstanceId());
		self::assertNull($certificate->getGeneration());
		self::assertSame('openssl', $certificate->getEngine());
	}

	public function testDoesNotCreateHybridScopeWhenExistingEngineConflicts(): void {
		$this->insertCertificate('8665-conflicting-engine', CRLStatus::ISSUED, null, 1, 'cfssl');

		$this->runMigration();

		$certificate = $this->crlMapper->findBySerialNumber('8665-conflicting-engine');
		self::assertNull($certificate->getInstanceId());
		self::assertSame(1, $certificate->getGeneration());
		self::assertSame('cfssl', $certificate->getEngine());
	}

	public function testSkipsAmbiguousRepairAfterCaRotation(): void {
		$this->insertCertificate('8665-ambiguous-generation', CRLStatus::ISSUED, 'abc123', null, 'openssl');

		$this->runMigration(2);

		$certificate = $this->crlMapper->findBySerialNumber('8665-ambiguous-generation');
		self::assertSame('abc123', $certificate->getInstanceId());
		self::assertNull($certificate->getGeneration());
		self::assertSame('openssl', $certificate->getEngine());
	}

	public function testSkipsRepairWithoutAuthoritativeCaId(): void {
		$this->insertCertificate('8665-no-ca-id', CRLStatus::ISSUED, null, null, '');

		$this->runMigration(1, 'invalid-ca-id');

		$certificate = $this->crlMapper->findBySerialNumber('8665-no-ca-id');
		self::assertNull($certificate->getInstanceId());
		self::assertNull($certificate->getGeneration());
	}

	public function testLeavesCompleteMetadataUntouched(): void {
		$this->insertCertificate('8665-complete', CRLStatus::ISSUED, 'existing-instance', 7, 'cfssl');

		$this->runMigration();

		$certificate = $this->crlMapper->findBySerialNumber('8665-complete');
		self::assertSame('existing-instance', $certificate->getInstanceId());
		self::assertSame(7, $certificate->getGeneration());
		self::assertSame('cfssl', $certificate->getEngine());
	}

	private function runMigration(int $generation = 1, ?string $caId = null): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')
			->willReturnCallback(static fn (string $app, string $key, string $default = ''): string => match ($key) {
				'ca_id' => $caId ?? 'libresign-ca-id:abc123_g:' . $generation . '_e:o',
				default => $default,
			});

		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('libresign_crl')->willReturn(true);
		$output = $this->createMock(IOutput::class);

		$migration = new Version18005Date20260923000000($appConfig, $this->connection);
		$migration->preSchemaChange($output, static fn (): ISchemaWrapper => $schema, []);
	}

	private function insertCertificate(
		string $serial,
		CRLStatus $status,
		?string $instanceId,
		?int $generation,
		string $engine,
	): void {
		$certificate = new Crl();
		$certificate->setSerialNumber($serial);
		$certificate->setOwner('issue-8665');
		$certificate->setStatus($status);
		$certificate->setIssuedAt(new DateTime('2026-01-26T00:00:00+00:00'));
		$certificate->setValidTo(new DateTime('+1 year'));
		$certificate->setEngine($engine);
		$certificate->setInstanceId($instanceId);
		$certificate->setGeneration($generation);
		if ($status === CRLStatus::REVOKED) {
			$certificate->setRevokedAt(new DateTime('2026-02-01T00:00:00+00:00'));
		}
		$this->crlMapper->insert($certificate);
	}

	private function deleteFixtures(): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('libresign_crl')
			->where($qb->expr()->eq('owner', $qb->createNamedParameter('issue-8665')))
			->executeStatement();
	}
}
