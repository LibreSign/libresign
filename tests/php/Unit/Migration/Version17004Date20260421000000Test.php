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
use OCA\Libresign\Migration\Version17004Date20260421000000;
use OCA\Libresign\Migration\Version18005Date20260923000000;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\Attributes\DataProvider;

final class Version17004Date20260421000000Test extends TestCase {
	private IDBConnection $connection;
	private CrlMapper $crlMapper;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->connection = \OCP\Server::get(IDBConnection::class);
		$this->crlMapper = new CrlMapper($this->connection);
		$this->deleteFixtures();
	}

	#[\Override]
	public function tearDown(): void {
		$this->deleteFixtures();
		parent::tearDown();
	}

	#[DataProvider('migrationClasses')]
	public function testBackfillRepairsRowWithAllLegacyMetadataMissing(string $migrationClass): void {
		$this->insertLegacyCertificate('8665-all-missing', null, null, '');

		$this->runMigration($migrationClass);

		$certificate = $this->crlMapper->findBySerialNumber('8665-all-missing');
		self::assertSame('abc123', $certificate->getInstanceId());
		self::assertSame(1, $certificate->getGeneration());
		self::assertSame('openssl', $certificate->getEngine());
	}

	#[DataProvider('migrationClasses')]
	public function testBackfillRepairsGenerationWhenItIsTheOnlyMissingField(string $migrationClass): void {
		$this->insertLegacyCertificate('8665-generation-missing', 'abc123', null, 'openssl');

		$this->runMigration($migrationClass);

		$certificate = $this->crlMapper->findBySerialNumber('8665-generation-missing');
		self::assertSame('abc123', $certificate->getInstanceId());
		self::assertSame(1, $certificate->getGeneration());
		self::assertSame('openssl', $certificate->getEngine());
	}

	#[DataProvider('migrationClasses')]
	public function testBackfillSkipsAmbiguousMetadataAfterCaRotation(string $migrationClass): void {
		$this->insertLegacyCertificate('8665-ambiguous-generation', 'abc123', null, 'openssl');

		$this->runMigration($migrationClass, 2);

		$certificate = $this->crlMapper->findBySerialNumber('8665-ambiguous-generation');
		self::assertSame('abc123', $certificate->getInstanceId());
		self::assertNull($certificate->getGeneration());
		self::assertSame('openssl', $certificate->getEngine());
	}

	public static function migrationClasses(): array {
		return [
			'original migration for fresh upgrades' => [Version17004Date20260421000000::class],
			'repair migration for already-upgraded instances' => [Version18005Date20260923000000::class],
		];
	}

	/**
	 * @param class-string $migrationClass
	 */
	private function runMigration(string $migrationClass, int $generation = 1): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')
			->willReturnCallback(static fn (string $app, string $key, string $default = ''): string => match ($key) {
				'certificate_engine' => 'openssl',
				'instance_id' => 'abc123',
				'ca_id' => 'libresign-ca-id:abc123_g:' . $generation . '_e:o',
				default => $default,
			});
		$appConfig->method('getValueInt')
			->willReturnCallback(static fn (string $app, string $key, int $default = 0): int => $key === 'ca_generation_counter' ? $generation : $default);

		$config = $this->createMock(IConfig::class);
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('libresign_crl')->willReturn(true);
		$output = $this->createMock(IOutput::class);

		$migration = new $migrationClass($config, $appConfig, $this->connection);
		$migration->preSchemaChange($output, static fn (): ISchemaWrapper => $schema, []);
	}

	private function insertLegacyCertificate(string $serial, ?string $instanceId, ?int $generation, string $engine): void {
		$certificate = new Crl();
		$certificate->setSerialNumber($serial);
		$certificate->setOwner('issue-8665');
		$certificate->setStatus(CRLStatus::ISSUED);
		$certificate->setIssuedAt(new DateTime('2026-01-26T00:00:00+00:00'));
		$certificate->setEngine($engine);
		$certificate->setInstanceId($instanceId);
		$certificate->setGeneration($generation);
		$this->crlMapper->insert($certificate);
	}

	private function deleteFixtures(): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->delete('libresign_crl')
			->where($qb->expr()->eq('owner', $qb->createNamedParameter('issue-8665')))
			->executeStatement();
	}
}
