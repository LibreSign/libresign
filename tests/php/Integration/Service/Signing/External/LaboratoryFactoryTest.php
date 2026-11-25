<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing\External;

use OCA\Libresign\Service\ExperimentalFeaturesService;
use OCA\Libresign\Service\Signing\ExternalIntegrationAvailability;
use OCA\Libresign\Service\Signing\ExternalIntegrationRecord;
use OCA\Libresign\Service\Signing\ExternalIntegrationStore;
use OCA\Libresign\Service\Signing\External\LaboratoryFactory;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Server;

/** @group DB */
final class LaboratoryFactoryTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private const ENDPOINT = 'https://synthetic-csc.example.org';
	private const HASH = 'abc123abc123abc123abc123abc123abc123abc123abc123abc123abc123abcd';

	private ExternalIntegrationStore $store;
	private ExternalIntegrationRecord $record;
	private bool $enabled = true;
	private string $pinnedEndpoint = self::ENDPOINT;
	private string $pinnedFingerprint = self::HASH;

	#[\Override]
	public function setUp(): void {
		parent::setUp();
		$this->store = Server::get(ExternalIntegrationStore::class);
		$created = $this->store->create(CscDriver::ID, 'Pinned synthetic CSC',
			['baseUrl' => self::ENDPOINT], []);
		$this->record = $this->store->setEnabled($created->id, $created->revision, true);
	}

	#[\Override]
	public function tearDown(): void {
		try {
			$qb = Server::get(IDBConnection::class)->getQueryBuilder();
			$qb->delete('libresign_signing_integration')
				->where($qb->expr()->eq('public_id', $qb->createNamedParameter($this->record->id)))
				->executeStatement();
		} finally {
			parent::tearDown();
		}
	}

	private function factory(): LaboratoryFactory {
		$gate = $this->createMock(ExperimentalFeaturesService::class);
		$gate->method('isEnabled')->willReturnCallback(fn (): bool => $this->enabled);
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueBool')->willReturnCallback(static fn (string $name, bool $default = false): bool => $default);
		$config->method('getSystemValueString')->willReturnCallback(fn (string $name, string $default = ''): string => match ($name) {
			'libresign_csc_synthetic_lab_url' => $this->pinnedEndpoint,
			'libresign_csc_synthetic_lab_certificate_sha256' => $this->pinnedFingerprint,
			default => $default,
		});
		$client = $this->createMock(IClientService::class);
		$client->expects($this->never())->method('newClient');
		return new LaboratoryFactory($this->store,
			new ExternalIntegrationAvailability($gate), Server::get(CscDriver::class),
			$client, $config);
	}

	public function testServerPinnedLaboratoryCanResolveConfiguredHttpsIntegration(): void {
		$result = $this->factory()->resolve($this->record->id);
		self::assertSame($this->record->id, $result['record']->id);
		$result['laboratory']->assertSyntheticLaboratory(self::ENDPOINT);
	}

	public function testOnlyOperatorPinnedEnabledLaboratoryAppearsInSignerChoices(): void {
		$other = $this->store->create(CscDriver::ID, 'Unpinned provider',
			['baseUrl' => 'https://other-csc.example.org'], []);
		try {
			$items = $this->factory()->listEligible();
			self::assertCount(1, $items);
			self::assertSame($this->record->id, $items[0]['id']);
			self::assertSame('Pinned synthetic CSC', $items[0]['name']);
		} finally {
			$qb = Server::get(IDBConnection::class)->getQueryBuilder();
			$qb->delete('libresign_signing_integration')->where(
				$qb->expr()->eq('public_id', $qb->createNamedParameter($other->id)),
			)->executeStatement();
		}
	}

	public function testAdminTokenCannotMakeAConfiguredIntegrationSynthetic(): void {
		$this->record = $this->store->update($this->record->id, $this->record->revision,
			$this->record->name, ['baseUrl' => self::ENDPOINT], ['accessToken' => 'not-signer-consent']);
		$this->expectException(\DomainException::class);
		$this->factory()->resolve($this->record->id);
	}

	public function testDisabledIntegrationRemainsUnavailable(): void {
		$this->record = $this->store->setEnabled($this->record->id, $this->record->revision, false);
		$this->expectException(\DomainException::class);
		$this->factory()->resolve($this->record->id);
	}

	public function testGlobalGateDisabledRemainsUnavailable(): void {
		$this->enabled = false;
		$this->expectException(\DomainException::class);
		$this->factory()->resolve($this->record->id);
	}

	public function testUnpinnedOrMismatchedEndpointIsRefused(): void {
		$this->pinnedEndpoint = 'https://other.example.org';
		$this->expectException(\DomainException::class);
		$this->factory()->resolve($this->record->id);
	}

	public function testMalformedCertificateFingerprintIsRefused(): void {
		$this->pinnedFingerprint = 'a-short-string';
		$this->expectException(\DomainException::class);
		$this->factory()->resolve($this->record->id);
	}

	public function testInvalidIntegrationIdentifierIsNotResolved(): void {
		$this->expectException(\OutOfBoundsException::class);
		$this->factory()->resolve('../../../config.php');
	}
}
