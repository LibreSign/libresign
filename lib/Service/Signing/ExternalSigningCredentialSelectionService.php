<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing;

use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscCredentialEligibility;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscDriver;
use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscIntegration;

/**
 * Provider-neutral signer-facing discovery, restricted to synthetic CSC v1.
 *
 * The real-provider path deliberately remains closed until individual CSC
 * authentication and hash-bound private-key authorization are implemented.
 * An administrator token alone cannot satisfy this requirement.
 *
 * No method accepts a client-provided actor ID, certificate or preparation.
 */
final class ExternalSigningCredentialSelectionService {
	/**
	 * Resolve a server-discovered credential and its permitted PDF profile.
	 *
	 * Keep this pure: the caller must authorize and refresh discovery first.
	 * No database, HTTP client or signer identity is required to test this
	 * decision. Never accept a client-submitted list of eligible credentials.
	 *
	 * @param list<array<string, mixed>> $choices
	 * @return array<string, mixed>
	 */
	public static function requireEligibleCredential(array $choices, string $credentialId, string $profile): array {
		foreach ($choices as $choice) {
			if (($choice['id'] ?? null) !== $credentialId) {
				continue;
			}
			if (is_array($choice['profiles'] ?? null)
				&& in_array($profile, $choice['profiles'], true)
				&& is_string($choice['certificateSha256'] ?? null)) {
				return $choice;
			}
			break;
		}
		throw new \DomainException('Selected CSC credential or PDF profile is not eligible');
	}

	public function __construct(
		private ExternalSigningAuthorizationService $authorization,
		private ExternalIntegrationStore $integrations,
		private ExternalIntegrationAvailability $availability,
		private CscDriver $cscDriver,
	) {
	}

	/**
	 * An internal laboratory adapter must be supplied by a trusted laboratory
	 * factory. Do not construct it from untrusted request values. The session
	 * creator independently rechecks this exact certificate and integration.
	 *
	 * @return array{integration:array{id:string,name:string,driverId:string},credentials:list<array<string,mixed>>,syntheticOnly:bool,personalSignerAuthorization:bool}
	 */
	public function discoverSynthetic(
		string $signRequestUuid,
		string $identificationMethod,
		string $identifyValue,
		#[\SensitiveParameter] string $signingToken,
		string $integrationId,
		CscIntegration $laboratory,
	): array {
		// Authenticate the current actor before even looking up a configured
		// integration, so an unauthorized guest cannot enumerate providers.
		$this->authorization->authorize($signRequestUuid, $identificationMethod, $identifyValue, $signingToken);
		$integration = $this->integrations->get($integrationId);
		$driver = $this->cscDriver->metadata();
		if ($integration->driverId !== CscDriver::ID
			|| !$this->availability->canStartSigning($integration, ($driver['available'] ?? false) === true)
			|| $integration->hasSecrets
			|| array_keys($integration->configuration) !== ['baseUrl']
			|| !is_string($integration->configuration['baseUrl'])) {
			throw new \DomainException('External signing integration is not eligible');
		}
		$laboratory->assertSyntheticLaboratory($integration->configuration['baseUrl']);
		$remote = $laboratory->certificates();
		if (count($remote) > 32) {
			throw new \DomainException('CSC laboratory returned too many credentials');
		}
		$eligibility = new CscCredentialEligibility();
		$choices = [];
		$seen = [];
		foreach ($remote as $certificate) {
			$id = $certificate->identifier;
			if ($id === '' || strlen($id) > 255 || isset($seen[$id])) {
				throw new \DomainException('Invalid or duplicate laboratory credential identifier');
			}
			$seen[$id] = true;
			try {
				$details = $eligibility->describe($certificate);
			} catch (\DomainException) {
				// Ineligible certificates are not exposed. A selected credential
				// must still be checked again before preparation and signHash.
				continue;
			}
			$choices[] = ['id' => $id, ...$details];
		}
		return [
			'integration' => [
				'id' => $integration->id,
				'name' => $integration->name,
				'driverId' => $integration->driverId,
			],
			'credentials' => $choices,
			'syntheticOnly' => true,
			'personalSignerAuthorization' => false,
		];
	}
}
