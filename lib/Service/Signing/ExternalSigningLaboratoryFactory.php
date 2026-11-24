<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscIntegration;
use OCP\Http\Client\IClientService;
use OCP\IConfig;

/**
 * Resolve an explicitly pinned synthetic CSC instance from trusted storage.
 *
 * No public endpoint may create a synthetic adapter directly or select the
 * remote URL, certificate or private signing mode from request parameters.
 * The server operator must pin the laboratory certificate and URL; an
 * administrator token or other integration secrets disable synthetic use.
 * There is deliberately no personal credential signing factory here.
 */
final class ExternalSigningLaboratoryFactory {
	public function __construct(
		private ExternalIntegrationStore $store,
		private ExternalIntegrationAvailability $availability,
		private CscDriver $driver,
		private IClientService $clientService,
		private IConfig $systemConfig,
	) {
	}

	/**
	 * Return only publicly named, operator-pinned synthetic integration
	 * instances. Enumerate after a separately verified LibreSign signer
	 * authorization; never expose an unrestricted admin driver catalog.
	 *
	 * @return list<array{id:string,name:string,driverId:string}>
	 */
	public function listEligible(): array {
		$items = [];
		$pinnedUrl = $this->systemConfig->getSystemValueString('libresign_csc_synthetic_lab_url', '');
		$pinnedHash = $this->systemConfig->getSystemValueString('libresign_csc_synthetic_lab_certificate_sha256', '');
		if ($pinnedUrl === '' || preg_match('/^[a-f0-9]{64}$/D', $pinnedHash) !== 1) {
			return [];
		}
		$cursor = 0;
		$scanned = 0;
		do {
			$page = $this->store->listPage($cursor, 100);
			foreach ($page['items'] as $record) {
				if (++$scanned > 10000) {
					throw new \DomainException('Too many signing integration instances to enumerate safely');
				}
				// Skip unrelated integrations without a database lookup or
				// HTTP client creation for each record in the page.
				if ($record->driverId !== CscDriver::ID || !$record->enabled || $record->hasSecrets
					|| ($record->configuration['baseUrl'] ?? null) !== $pinnedUrl) {
					continue;
				}
				try {
					$this->resolve($record->id);
				} catch (\DomainException|\InvalidArgumentException|\OutOfBoundsException|\LogicException) {
					continue;
				}
				$items[] = ['id' => $record->id, 'name' => $record->name, 'driverId' => $record->driverId];
			}
			$cursor = $page['nextCursor'];
		} while ($cursor !== null);
		return $items;
	}

	/** @return array{record: ExternalIntegrationRecord, laboratory: CscIntegration} */
	public function resolve(string $integrationId): array {
		if (preg_match('/^[a-f0-9]{32}$/D', $integrationId) !== 1) {
			throw new \OutOfBoundsException('External signing integration not found');
		}
		$integration = $this->store->get($integrationId);
		$driver = $this->driver->metadata();
		if ($integration->driverId !== CscDriver::ID
			|| !$this->availability->canStartSigning($integration, ($driver['available'] ?? false) === true)
			|| $integration->hasSecrets
			|| array_keys($integration->configuration) !== ['baseUrl']
			|| !is_string($integration->configuration['baseUrl'])) {
			throw new \DomainException('No eligible configured synthetic signing laboratory');
		}
		$configuration = ['baseUrl' => $integration->configuration['baseUrl']];
		$laboratory = CscIntegration::fromConfiguredSyntheticLab(
			$this->clientService, $this->systemConfig, $configuration);
		$laboratory->assertSyntheticLaboratory($configuration['baseUrl']);
		return ['record' => $integration, 'laboratory' => $laboratory];
	}
}
