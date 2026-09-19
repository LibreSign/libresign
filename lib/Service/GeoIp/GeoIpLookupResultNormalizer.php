<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\GeoIp;

use OCA\Libresign\Enum\SignerIpGeolocationStatus;
use OCA\Libresign\Enum\SignerIpGeolocationUnavailableReason;
use OCA\Libresign\Vendor\GeoIp2\Model\City;

/**
 * Normalizes MaxMind City lookup results into signer metadata.
 *
 * Only fields present in the database result are stored. Postal address data is
 * intentionally omitted.
 */
class GeoIpLookupResultNormalizer {
	/**
	 * @return array{
	 *     status: string,
	 *     sourceIp?: string,
	 *     countryCode?: string,
	 *     country?: string,
	 *     regionCode?: string,
	 *     region?: string,
	 *     city?: string,
	 *     latitude?: float,
	 *     longitude?: float,
	 *     accuracyRadius?: int,
	 * }
	 */
	public function normalizeResolved(City $city, ?string $sourceIp): array {
		$result = [
			'status' => SignerIpGeolocationStatus::RESOLVED->value,
		];

		if ($sourceIp !== null && $sourceIp !== '') {
			$result['sourceIp'] = $sourceIp;
		}

		$countryCode = $city->country->isoCode;
		if (is_string($countryCode) && $countryCode !== '') {
			$result['countryCode'] = $countryCode;
		}

		$countryName = $city->country->name;
		if (is_string($countryName) && $countryName !== '') {
			$result['country'] = $countryName;
		}

		$regionCode = $city->mostSpecificSubdivision->isoCode;
		if (is_string($regionCode) && $regionCode !== '') {
			$result['regionCode'] = $regionCode;
		}

		$regionName = $city->mostSpecificSubdivision->name;
		if (is_string($regionName) && $regionName !== '') {
			$result['region'] = $regionName;
		}

		$cityName = $city->city->name;
		if (is_string($cityName) && $cityName !== '') {
			$result['city'] = $cityName;
		}

		$latitude = $city->location->latitude;
		$longitude = $city->location->longitude;
		if ($latitude !== null && $longitude !== null) {
			$result['latitude'] = $latitude;
			$result['longitude'] = $longitude;

			$accuracyRadius = $city->location->accuracyRadius;
			if ($accuracyRadius !== null) {
				$result['accuracyRadius'] = $accuracyRadius;
			}
		}

		return $result;
	}

	/**
	 * @return array{status: string, sourceIp?: string}
	 */
	public function normalizeNotFound(?string $sourceIp): array {
		$result = [
			'status' => SignerIpGeolocationStatus::NOT_FOUND->value,
		];
		if ($sourceIp !== null && $sourceIp !== '') {
			$result['sourceIp'] = $sourceIp;
		}
		return $result;
	}

	/**
	 * @return array{status: string, sourceIp?: string, reason: string}
	 */
	public function normalizeUnavailable(
		SignerIpGeolocationUnavailableReason $reason,
		?string $sourceIp,
	): array {
		$result = [
			'status' => SignerIpGeolocationStatus::UNAVAILABLE->value,
			'reason' => $reason->value,
		];
		if ($sourceIp !== null && $sourceIp !== '') {
			$result['sourceIp'] = $sourceIp;
		}
		return $result;
	}
}
