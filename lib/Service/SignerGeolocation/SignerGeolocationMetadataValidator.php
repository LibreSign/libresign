<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignerGeolocation;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\SignerGeolocationCollectionStatus;
use OCA\Libresign\Enum\SignerGeolocationMode;
use OCA\Libresign\Exception\LibresignException;
use OCP\IL10N;

class SignerGeolocationMetadataValidator {
	public const METADATA_GEOLOCATION_KEY = 'geolocation';

	public function __construct(
		private SignerGeolocationPolicyService $signerGeolocationPolicyService,
		private IL10N $l10n,
	) {
	}

	/**
	 * @return array{
	 *     status: string,
	 *     latitude?: float,
	 *     longitude?: float,
	 *     accuracy?: float,
	 *     timestamp?: int,
	 * }|null
	 */
	public function normalize(mixed $rawValue): ?array {
		if ($rawValue === null || $rawValue === []) {
			return null;
		}

		if (!is_array($rawValue)) {
			throw new LibresignException($this->l10n->t('Invalid geolocation payload.'));
		}

		$status = SignerGeolocationCollectionStatus::tryFrom((string)($rawValue['status'] ?? ''));
		if ($status === null) {
			throw new LibresignException($this->l10n->t('Invalid geolocation collection status.'));
		}

		$normalized = [
			'status' => $status->value,
		];

		if ($status === SignerGeolocationCollectionStatus::COLLECTED) {
			if (!array_key_exists('latitude', $rawValue) || !array_key_exists('longitude', $rawValue)) {
				throw new LibresignException($this->l10n->t('Collected geolocation requires latitude and longitude.'));
			}

			$latitude = filter_var($rawValue['latitude'], FILTER_VALIDATE_FLOAT);
			$longitude = filter_var($rawValue['longitude'], FILTER_VALIDATE_FLOAT);
			if ($latitude === false || $longitude === false) {
				throw new LibresignException($this->l10n->t('Invalid geolocation coordinates.'));
			}

			if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
				throw new LibresignException($this->l10n->t('Geolocation coordinates are out of range.'));
			}

			$normalized['latitude'] = (float)$latitude;
			$normalized['longitude'] = (float)$longitude;

			if (array_key_exists('accuracy', $rawValue) && $rawValue['accuracy'] !== null && $rawValue['accuracy'] !== '') {
				$accuracy = filter_var($rawValue['accuracy'], FILTER_VALIDATE_FLOAT);
				if ($accuracy === false || $accuracy < 0.0) {
					throw new LibresignException($this->l10n->t('Invalid geolocation accuracy.'));
				}
				$normalized['accuracy'] = (float)$accuracy;
			}

			if (array_key_exists('timestamp', $rawValue) && $rawValue['timestamp'] !== null && $rawValue['timestamp'] !== '') {
				$timestamp = filter_var($rawValue['timestamp'], FILTER_VALIDATE_INT);
				if ($timestamp === false || $timestamp < 0) {
					throw new LibresignException($this->l10n->t('Invalid geolocation timestamp.'));
				}
				$normalized['timestamp'] = (int)$timestamp;
			}
		}

		return $normalized;
	}

	public function validateSubmission(SignRequest $signRequest, ?array $geolocation): void {
		$requirement = $this->signerGeolocationPolicyService->getFrozenRequirement($signRequest)
			?? SignerGeolocationMode::DISABLED;

		if ($requirement === SignerGeolocationMode::DISABLED) {
			if ($geolocation !== null) {
				throw new LibresignException($this->l10n->t('Geolocation is not enabled for this signature request.'));
			}
			return;
		}

		if ($geolocation === null) {
			if ($requirement === SignerGeolocationMode::REQUIRED) {
				throw new LibresignException($this->l10n->t('Geolocation is required to sign this document.'));
			}
			return;
		}

		if ($requirement === SignerGeolocationMode::REQUIRED
			&& ($geolocation['status'] ?? '') !== SignerGeolocationCollectionStatus::COLLECTED->value) {
			throw new LibresignException($this->l10n->t('Geolocation is required to sign this document.'));
		}
	}
}
