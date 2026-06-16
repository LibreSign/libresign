<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Db;

use OCA\Libresign\Db\Crl;
use OCA\Libresign\Db\CrlMapper;
use OCA\Libresign\Enum\CRLStatus;
use OCP\IDBConnection;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @group DB
 */
final class CrlMapperDbTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private CrlMapper $crlMapper;
	private IDBConnection $connection;

	public function setUp(): void {
		parent::setUp();
		$this->crlMapper = Server::get(CrlMapper::class);
		$this->connection = Server::get(IDBConnection::class);
		$this->cleanupCrlTable();
	}

	public function tearDown(): void {
		$this->cleanupCrlTable();
		parent::tearDown();
	}

	#[DataProvider('generatedCrlScopesProvider')]
	public function testListGeneratedCrlScopesReturnsDistinctCanonicalScopes(array $rows, array $expected): void {
		foreach ($rows as $row) {
			$this->crlMapper->insert($this->buildCertificate(
				$row['serialNumber'],
				$row['instanceId'],
				$row['generation'],
				$row['engine'],
			));
		}

		$this->assertSame($expected, $this->crlMapper->listGeneratedCrlScopes());
	}

	public static function generatedCrlScopesProvider(): array {
		return [
			'deduplicates canonical scopes and skips legacy rows' => [
				'rows' => [
					['serialNumber' => '1001', 'instanceId' => 'inst-a', 'generation' => 1, 'engine' => 'openssl'],
					['serialNumber' => '1002', 'instanceId' => 'inst-a', 'generation' => 1, 'engine' => 'openssl'],
					['serialNumber' => '1003', 'instanceId' => 'inst-b', 'generation' => 2, 'engine' => 'cfssl'],
					['serialNumber' => '1004', 'instanceId' => 'inst-c', 'generation' => 3, 'engine' => 'unknown'],
					['serialNumber' => '1005', 'instanceId' => null, 'generation' => null, 'engine' => 'openssl'],
					['serialNumber' => '1006', 'instanceId' => '', 'generation' => 4, 'engine' => 'openssl'],
				],
				'expected' => [
					['instanceId' => 'inst-a', 'generation' => 1, 'engineType' => 'o'],
					['instanceId' => 'inst-b', 'generation' => 2, 'engineType' => 'c'],
				],
			],
			'returns empty list for empty table' => [
				'rows' => [],
				'expected' => [],
			],
			'orders distinct scopes deterministically' => [
				'rows' => [
					['serialNumber' => '2001', 'instanceId' => 'inst-b', 'generation' => 2, 'engine' => 'cfssl'],
					['serialNumber' => '2002', 'instanceId' => 'inst-a', 'generation' => 2, 'engine' => 'cfssl'],
					['serialNumber' => '2003', 'instanceId' => 'inst-a', 'generation' => 1, 'engine' => 'openssl'],
				],
				'expected' => [
					['instanceId' => 'inst-a', 'generation' => 1, 'engineType' => 'o'],
					['instanceId' => 'inst-a', 'generation' => 2, 'engineType' => 'c'],
					['instanceId' => 'inst-b', 'generation' => 2, 'engineType' => 'c'],
				],
			],
		];
	}

	private function cleanupCrlTable(): void {
		$this->connection->getQueryBuilder()
			->delete('libresign_crl')
			->executeStatement();
	}

	private function buildCertificate(
		string $serialNumber,
		?string $instanceId,
		?int $generation,
		string $engine,
	): Crl {
		$certificate = new Crl();
		$certificate->setSerialNumber($serialNumber);
		$certificate->setOwner('scope-tester');
		$certificate->setStatus(CRLStatus::ISSUED);
		$certificate->setIssuedAt(new \DateTime('2026-06-14 00:00:00', new \DateTimeZone('UTC')));
		$certificate->setInstanceId($instanceId);
		$certificate->setGeneration($generation);
		$certificate->setEngine($engine);

		return $certificate;
	}
}
