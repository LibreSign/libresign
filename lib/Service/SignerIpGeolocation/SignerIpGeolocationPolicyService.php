<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignerIpGeolocation;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Service\GeoIp\GeoIpLookupService;
use OCA\Libresign\Service\Policy\Provider\SignerIpGeolocation\SignerIpGeolocationPolicy;
use OCA\Libresign\Service\Policy\Provider\SignerIpGeolocation\SignerIpGeolocationPolicyValue;
use OCA\Libresign\Service\Policy\ResolvesFrozenFilePolicySnapshot;
use OCA\Libresign\Service\SignerGeolocation\SignerGeolocationMetadataValidator;
use OCP\IRequest;

/**
 * Reads frozen IP geolocation policy from a signature request and collects IP
 * metadata at signing time. The live PolicyService is never consulted.
 */
class SignerIpGeolocationPolicyService {
	use ResolvesFrozenFilePolicySnapshot;

	public function __construct(
		private FileMapper $fileMapper,
		private GeoIpLookupService $geoIpLookupService,
	) {
	}

	/**
	 * @return array{mode: string}
	 */
	public function getPolicyValue(?FileEntity $file = null): array {
		return $this->findSnapshot($file) ?? SignerIpGeolocationPolicyValue::defaults();
	}

	public function isEnabled(?FileEntity $file = null): bool {
		return SignerIpGeolocationPolicyValue::isEnabled($this->getPolicyValue($file));
	}

	/**
	 * Collect IP geolocation metadata when the frozen snapshot enables it.
	 *
	 * Never blocks signing: lookup failures are stored as unavailable/not_found.
	 *
	 * @return array<string, mixed>|null null when IP geolocation is disabled for this request
	 */
	public function collectMetadata(?FileEntity $file, IRequest $request): ?array {
		if (!$this->isEnabled($file)) {
			return null;
		}

		$remoteAddress = $request->getRemoteAddress();
		$sourceIp = is_string($remoteAddress) ? trim($remoteAddress) : '';
		return $this->geoIpLookupService->lookup($sourceIp === '' ? null : $sourceIp);
	}

	/**
	 * Merge IP metadata into the signing metadata bag under geolocation.ip.
	 *
	 * @param array<string, mixed> $metadata
	 * @param array<string, mixed> $ipGeolocation
	 * @return array<string, mixed>
	 */
	public function mergeIntoMetadata(array $metadata, array $ipGeolocation): array {
		$geolocation = $metadata[SignerGeolocationMetadataValidator::METADATA_GEOLOCATION_KEY] ?? [];
		if (!is_array($geolocation)) {
			$geolocation = [];
		}
		$geolocation[SignerGeolocationMetadataValidator::METADATA_IP_KEY] = $ipGeolocation;
		$metadata[SignerGeolocationMetadataValidator::METADATA_GEOLOCATION_KEY] = $geolocation;
		return $metadata;
	}

	protected function getFrozenPolicyKey(): string {
		return SignerIpGeolocationPolicy::KEY;
	}

	/**
	 * @return array{mode: string}|null
	 */
	protected function normalizeFrozenPolicyEffectiveValue(mixed $value): ?array {
		return SignerIpGeolocationPolicyValue::normalize($value);
	}
}
