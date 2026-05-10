<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\IdentifyMethods;

use OCA\Libresign\Service\IdentifyMethodService;

final class IdentifyMethodsPolicyValue {
	/**
	 * @return array{factors: list<array<string, mixed>>, can_create_account?: bool}
	 */
	public static function normalize(mixed $rawValue, ?IdentifyMethodService $identifyMethodService = null): array {
		$globalCanCreateAccount = null;
		$sharedMinimumTotalVerifiedFactors = null;
		if (is_string($rawValue)) {
			$decoded = json_decode($rawValue, true);
			if (is_array($decoded)) {
				if (array_is_list($decoded)) {
					$rawValue = $decoded;
				} elseif (isset($decoded['factors']) && is_array($decoded['factors'])) {
					$rawValue = $decoded['factors'];
					$sharedMinimumTotalVerifiedFactors = self::normalizeMinimumTotalVerifiedFactors($decoded['minimumTotalVerifiedFactors'] ?? null);
				}
				if (array_key_exists('can_create_account', $decoded)) {
					$globalCanCreateAccount = (bool)$decoded['can_create_account'];
				}
			}
		} elseif (is_array($rawValue) && !array_is_list($rawValue)) {
			$candidate = $rawValue;
			if (isset($candidate['factors']) && is_array($candidate['factors'])) {
				$sharedMinimumTotalVerifiedFactors = self::normalizeMinimumTotalVerifiedFactors($candidate['minimumTotalVerifiedFactors'] ?? null);
				$rawValue = $candidate['factors'];
			}
			if (array_key_exists('can_create_account', $candidate)) {
				$globalCanCreateAccount = (bool)$candidate['can_create_account'];
			}
		}

		if (!is_array($rawValue)) {
			return [
				'factors' => [],
			];
		}

		$normalized = [];
		$legacyGlobalCanCreateAccount = $globalCanCreateAccount;
		foreach ($rawValue as $entry) {
			if (is_string($entry)) {
				$name = trim($entry);
				if ($name === '') {
					continue;
				}

				$normalizedEntry = [
					'name' => $name,
					'enabled' => true,
					'signatureMethods' => [],
				];

				if ($sharedMinimumTotalVerifiedFactors !== null) {
					$normalizedEntry['minimumTotalVerifiedFactors'] = $sharedMinimumTotalVerifiedFactors;
				}

				$normalized[] = $normalizedEntry;
				continue;
			}

			if (!is_array($entry)) {
				continue;
			}

			$name = isset($entry['name']) && is_string($entry['name'])
				? trim($entry['name'])
				: '';
			if ($name === '') {
				continue;
			}

			$signatureMethods = [];
			if (isset($entry['signatureMethods']) && is_array($entry['signatureMethods'])) {
				$isList = array_is_list($entry['signatureMethods']);
				if ($isList) {
					foreach ($entry['signatureMethods'] as $signatureMethodName) {
						if (!is_string($signatureMethodName) || trim($signatureMethodName) === '') {
							continue;
						}
						$signatureMethods[$signatureMethodName] = ['enabled' => false];
					}
				} else {
					foreach ($entry['signatureMethods'] as $signatureMethodName => $signatureMethodConfig) {
						if (!is_string($signatureMethodName) || trim($signatureMethodName) === '') {
							continue;
						}

						if (is_string($signatureMethodConfig)) {
							$signatureMethods[$signatureMethodName] = [
								'enabled' => false,
								'label' => $signatureMethodConfig,
							];
							continue;
						}

						if (!is_array($signatureMethodConfig)) {
							continue;
						}

						$normalizedSignatureMethod = [];
						if (array_key_exists('enabled', $signatureMethodConfig)) {
							$normalizedSignatureMethod['enabled'] = (bool)$signatureMethodConfig['enabled'];
						}

						if (isset($signatureMethodConfig['label']) && is_string($signatureMethodConfig['label'])) {
							$normalizedSignatureMethod['label'] = $signatureMethodConfig['label'];
						}

						$signatureMethods[$signatureMethodName] = $normalizedSignatureMethod;
					}
				}
			}

			if ($signatureMethods === [] && isset($entry['availableSignatureMethods']) && is_array($entry['availableSignatureMethods'])) {
				foreach ($entry['availableSignatureMethods'] as $signatureMethodName) {
					if (!is_string($signatureMethodName) || trim($signatureMethodName) === '') {
						continue;
					}
					$signatureMethods[$signatureMethodName] = ['enabled' => false];
				}
			}

			$normalizedEntry = [
				'name' => $name,
				'enabled' => array_key_exists('enabled', $entry) ? (bool)$entry['enabled'] : true,
				'signatureMethods' => $signatureMethods,
			];

			if (isset($entry['friendly_name']) && is_string($entry['friendly_name'])) {
				$normalizedEntry['friendly_name'] = $entry['friendly_name'];
			}

			if (array_key_exists('can_create_account', $entry)) {
				if ($legacyGlobalCanCreateAccount === null) {
					$legacyGlobalCanCreateAccount = (bool)$entry['can_create_account'];
				}
			}

			$minimumTotalVerifiedFactors = self::normalizeMinimumTotalVerifiedFactors($entry['minimumTotalVerifiedFactors'] ?? null)
				?? $sharedMinimumTotalVerifiedFactors;
			if ($minimumTotalVerifiedFactors !== null) {
				$normalizedEntry['minimumTotalVerifiedFactors'] = $minimumTotalVerifiedFactors;
			}

			$requirement = self::normalizeRequirement($entry['requirement'] ?? null, $entry['mandatory'] ?? null);
			if ($requirement !== null) {
				$normalizedEntry['requirement'] = $requirement;
				$normalizedEntry['mandatory'] = $requirement === 'required';
			} elseif (array_key_exists('mandatory', $entry)) {
				$normalizedEntry['mandatory'] = (bool)$entry['mandatory'];
			}

			if (isset($entry['signatureMethodEnabled']) && is_string($entry['signatureMethodEnabled'])) {
				$normalizedEntry['signatureMethodEnabled'] = $entry['signatureMethodEnabled'];
			}

			$normalized[] = $normalizedEntry;
		}

		if ($identifyMethodService !== null) {
			$friendlyNames = $identifyMethodService->getFriendlyNamesMap();
			foreach ($normalized as &$entry) {
				if (!isset($entry['friendly_name']) && isset($entry['name'], $friendlyNames[$entry['name']])) {
					$entry['friendly_name'] = $friendlyNames[$entry['name']];
				}
			}
			unset($entry);
		}

		$payload = [
			'factors' => $normalized,
		];
		if ($legacyGlobalCanCreateAccount !== null) {
			$payload['can_create_account'] = $legacyGlobalCanCreateAccount;
		}

		return $payload;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function extractFactors(array $normalizedPayload): array {
		if (isset($normalizedPayload['factors']) && is_array($normalizedPayload['factors'])) {
			return array_values(array_filter($normalizedPayload['factors'], static fn (mixed $entry): bool => is_array($entry)));
		}

		if (array_is_list($normalizedPayload)) {
			return array_values(array_filter($normalizedPayload, static fn (mixed $entry): bool => is_array($entry)));
		}

		return [];
	}

	public static function resolveGlobalCanCreateAccount(array $normalizedPayload): ?bool {
		if (array_key_exists('can_create_account', $normalizedPayload)) {
			return (bool)$normalizedPayload['can_create_account'];
		}

		$factors = self::extractFactors($normalizedPayload);
		foreach ($factors as $entry) {
			if (array_key_exists('can_create_account', $entry)) {
				return (bool)$entry['can_create_account'];
			}
		}

		return null;
	}

	private static function normalizeRequirement(mixed $requirement, mixed $mandatory): ?string {
		if ($requirement === 'required' || $requirement === 'optional') {
			return $requirement;
		}

		if ($mandatory === null) {
			return null;
		}

		return (bool)$mandatory ? 'required' : 'optional';
	}

	private static function normalizeMinimumTotalVerifiedFactors(mixed $value): ?int {
		if (!is_numeric($value)) {
			return null;
		}

		$normalized = (int)$value;
		if ($normalized < 1) {
			return null;
		}

		return $normalized;
	}
}
