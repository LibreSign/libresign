<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\IdentifyMethods;

use OCA\Libresign\Enum\IdentifyMethodRequirement;
use OCA\Libresign\Service\IdentifyMethodService;

final class IdentifyMethodsPolicyValue {
	/**
	 * @return array{factors: list<array<string, mixed>>, can_create_account?: bool}
	 */
	public static function normalize(mixed $rawValue, ?IdentifyMethodService $identifyMethodService = null): array {
		$preparedInput = self::prepareInput($rawValue);
		$catalogFactors = self::normalizeCatalogFactors($identifyMethodService);
		// Use service-provided defaults when policy is empty and service is available
		$isEmpty = $preparedInput['factors'] === null || (is_array($preparedInput['factors']) && count($preparedInput['factors']) === 0);
		if ($isEmpty) {
			$defaultFactors = [];
			if ($identifyMethodService !== null) {
				$defaultFactors = $identifyMethodService->getDefaultIdentifyMethodsPolicy();
				if ($defaultFactors === []) {
					$friendlyNames = $identifyMethodService->getFriendlyNamesMap();
					foreach ($friendlyNames as $name => $friendlyName) {
						$defaultFactors[] = [
							'name' => $name,
							'enabled' => true,
							'signatureMethods' => [],
							'friendly_name' => $friendlyName,
						];
					}
				}
			}
			$normalization = self::normalizeFactors($defaultFactors, null, null);
			$normalized = $normalization['factors'];
			if ($catalogFactors !== []) {
				$normalized = self::mergeFactorsWithCatalog($normalized, $catalogFactors);
			}

			if ($identifyMethodService !== null) {
				$normalized = self::enrichFriendlyNames($normalized, $identifyMethodService->getFriendlyNamesMap());
			}
			$normalized = self::canonicalizeFactorEntries($normalized);

			return [
				'factors' => $normalized,
			];
		}
		$normalization = self::normalizeFactors(
			$preparedInput['factors'],
			$preparedInput['sharedMinimumTotalVerifiedFactors'],
			$preparedInput['globalCanCreateAccount'],
		);
		$normalized = $normalization['factors'];
		$legacyGlobalCanCreateAccount = $normalization['globalCanCreateAccount'];
		if ($catalogFactors !== []) {
			$normalized = self::mergeFactorsWithCatalog($normalized, $catalogFactors, false);
		}

		if ($identifyMethodService !== null) {
			$normalized = self::enrichFriendlyNames($normalized, $identifyMethodService->getFriendlyNamesMap());
		}
		$normalized = self::canonicalizeFactorEntries($normalized);

		$payload = [
			'factors' => $normalized,
		];
		if ($legacyGlobalCanCreateAccount !== null) {
			$payload['can_create_account'] = $legacyGlobalCanCreateAccount;
		}

		return $payload;
	}

	/**
	 * @return array{factors: list<mixed>|null, sharedMinimumTotalVerifiedFactors: ?int, globalCanCreateAccount: ?bool}
	 */
	private static function prepareInput(mixed $rawValue): array {
		$globalCanCreateAccount = null;
		$sharedMinimumTotalVerifiedFactors = null;
		$factors = null;

		if (is_string($rawValue)) {
			$decoded = json_decode($rawValue, true);
			if (is_array($decoded)) {
				return self::prepareInputFromArrayPayload($decoded);
			}
		}

		if (is_array($rawValue)) {
			if (array_is_list($rawValue)) {
				$factors = $rawValue;
			} else {
				return self::prepareInputFromArrayPayload($rawValue);
			}
		}

		return [
			'factors' => $factors,
			'sharedMinimumTotalVerifiedFactors' => $sharedMinimumTotalVerifiedFactors,
			'globalCanCreateAccount' => $globalCanCreateAccount,
		];
	}

	/**
	 * @param array<string, mixed>|list<mixed> $payload
	 * @return array{factors: list<mixed>|null, sharedMinimumTotalVerifiedFactors: ?int, globalCanCreateAccount: ?bool}
	 */
	private static function prepareInputFromArrayPayload(array $payload): array {
		if (array_is_list($payload)) {
			return [
				'factors' => $payload,
				'sharedMinimumTotalVerifiedFactors' => null,
				'globalCanCreateAccount' => null,
			];
		}

		$factors = null;
		$sharedMinimumTotalVerifiedFactors = null;
		if (isset($payload['factors']) && is_array($payload['factors'])) {
			$factors = array_values($payload['factors']);
			$sharedMinimumTotalVerifiedFactors = self::normalizeMinimumTotalVerifiedFactors($payload['minimumTotalVerifiedFactors'] ?? null);
		}

		$globalCanCreateAccount = null;
		if (array_key_exists('can_create_account', $payload)) {
			$globalCanCreateAccount = (bool)$payload['can_create_account'];
		}

		return [
			'factors' => $factors,
			'sharedMinimumTotalVerifiedFactors' => $sharedMinimumTotalVerifiedFactors,
			'globalCanCreateAccount' => $globalCanCreateAccount,
		];
	}

	/**
	 * @param list<mixed> $rawFactors
	 * @return array{factors: list<array<string, mixed>>, globalCanCreateAccount: ?bool}
	 */
	private static function normalizeFactors(
		array $rawFactors,
		?int $sharedMinimumTotalVerifiedFactors,
		?bool $globalCanCreateAccount,
	): array {
		$normalized = [];
		foreach ($rawFactors as $entry) {
			if (is_string($entry)) {
				$normalizedEntry = self::normalizeLegacyStringEntry($entry, $sharedMinimumTotalVerifiedFactors);
				if ($normalizedEntry !== null) {
					$normalized[] = $normalizedEntry;
				}
				continue;
			}

			if (!is_array($entry)) {
				continue;
			}

			$normalizedEntry = self::normalizeFactorEntry($entry, $sharedMinimumTotalVerifiedFactors, $globalCanCreateAccount);
			if ($normalizedEntry === null) {
				continue;
			}

			$normalized[] = $normalizedEntry['entry'];
			if ($globalCanCreateAccount === null && $normalizedEntry['globalCanCreateAccount'] !== null) {
				$globalCanCreateAccount = $normalizedEntry['globalCanCreateAccount'];
			}
		}

		return [
			'factors' => $normalized,
			'globalCanCreateAccount' => $globalCanCreateAccount,
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function normalizeCatalogFactors(?IdentifyMethodService $identifyMethodService): array {
		if ($identifyMethodService === null) {
			return [];
		}

		return self::extractFactors(self::normalize($identifyMethodService->getIdentifyMethodsCatalogSettings()));
	}

	/**
	 * @param list<array<string, mixed>> $policyFactors
	 * @param list<array<string, mixed>> $catalogFactors
	 * @param bool $addMissingFromCatalog When true (default), factors present in the catalog but absent
	 *                                     from the policy are appended as disabled entries (used for the
	 *                                     empty-policy path).  When false, only the factors already in
	 *                                     the policy are kept (in policy order) and each is enriched
	 *                                     with catalog metadata such as labels and friendly names.
	 * @return list<array<string, mixed>>
	 */
	private static function mergeFactorsWithCatalog(array $policyFactors, array $catalogFactors, bool $addMissingFromCatalog = true): array {
		if ($catalogFactors === []) {
			return $policyFactors;
		}

		if (!$addMissingFromCatalog) {
			// Enrich-only mode: preserve policy factors in policy order, enrich each from catalog.
			$catalogFactorsByName = [];
			foreach ($catalogFactors as $catalogFactor) {
				if (!isset($catalogFactor['name']) || !is_string($catalogFactor['name']) || $catalogFactor['name'] === '') {
					continue;
				}
				$catalogFactorsByName[$catalogFactor['name']] = $catalogFactor;
			}

			$mergedFactors = [];
			foreach ($policyFactors as $policyFactor) {
				if (!isset($policyFactor['name']) || !is_string($policyFactor['name']) || $policyFactor['name'] === '') {
					$mergedFactors[] = $policyFactor;
					continue;
				}
				$catalogFactor = $catalogFactorsByName[$policyFactor['name']] ?? null;
				if ($catalogFactor === null) {
					$mergedFactors[] = $policyFactor;
					continue;
				}
				$mergedFactors[] = self::mergeFactorWithCatalog($policyFactor, $catalogFactor);
			}
			return $mergedFactors;
		}

		// Full merge mode: catalog-ordered, catalog factors missing from the policy are added as disabled.
		$policyFactorsByName = [];
		foreach ($policyFactors as $policyFactor) {
			if (!isset($policyFactor['name']) || !is_string($policyFactor['name']) || $policyFactor['name'] === '') {
				continue;
			}

			$policyFactorsByName[$policyFactor['name']] = $policyFactor;
		}

		$catalogFactorNames = [];
		$mergedFactors = [];
		foreach ($catalogFactors as $catalogFactor) {
			if (!isset($catalogFactor['name']) || !is_string($catalogFactor['name']) || $catalogFactor['name'] === '') {
				continue;
			}

			$catalogFactorNames[$catalogFactor['name']] = true;
			$policyFactor = $policyFactorsByName[$catalogFactor['name']] ?? null;
			if ($policyFactor === null) {
				$mergedFactors[] = self::buildCatalogFactor($catalogFactor);
				continue;
			}

			$mergedFactors[] = self::mergeFactorWithCatalog($policyFactor, $catalogFactor);
		}

		foreach ($policyFactors as $policyFactor) {
			if (!isset($policyFactor['name']) || !is_string($policyFactor['name']) || $policyFactor['name'] === '') {
				$mergedFactors[] = $policyFactor;
				continue;
			}

			if (!isset($catalogFactorNames[$policyFactor['name']])) {
				$mergedFactors[] = $policyFactor;
			}
		}

		return $mergedFactors;
	}

	/**
	 * @param array<string, mixed> $catalogFactor
	 * @return array<string, mixed>
	 */
	private static function buildCatalogFactor(array $catalogFactor): array {
		$catalogFactor['enabled'] = false;

		return $catalogFactor;
	}

	/**
	 * @param array<string, mixed> $policyFactor
	 * @param array<string, mixed> $catalogFactor
	 * @return array<string, mixed>
	 */
	private static function mergeFactorWithCatalog(array $policyFactor, array $catalogFactor): array {
		$mergedFactor = $policyFactor;

		if (!isset($mergedFactor['friendly_name']) && isset($catalogFactor['friendly_name'])) {
			$mergedFactor['friendly_name'] = $catalogFactor['friendly_name'];
		}

		if (!isset($mergedFactor['requirement']) && isset($catalogFactor['requirement'])) {
			$mergedFactor['requirement'] = $catalogFactor['requirement'];
		}

		if (!isset($mergedFactor['minimumTotalVerifiedFactors']) && isset($catalogFactor['minimumTotalVerifiedFactors'])) {
			$mergedFactor['minimumTotalVerifiedFactors'] = $catalogFactor['minimumTotalVerifiedFactors'];
		}

		if (!isset($mergedFactor['signatureMethodEnabled']) && isset($catalogFactor['signatureMethodEnabled'])) {
			$mergedFactor['signatureMethodEnabled'] = $catalogFactor['signatureMethodEnabled'];
		}

		$catalogSignatureMethods = isset($catalogFactor['signatureMethods']) && is_array($catalogFactor['signatureMethods'])
			? $catalogFactor['signatureMethods']
			: [];
		$policySignatureMethods = isset($policyFactor['signatureMethods']) && is_array($policyFactor['signatureMethods'])
			? $policyFactor['signatureMethods']
			: [];
		$mergedFactor['signatureMethods'] = self::mergeSignatureMethods($catalogSignatureMethods, $policySignatureMethods);

		return $mergedFactor;
	}

	/**
	 * @param array<string, array<string, mixed>> $catalogSignatureMethods
	 * @param array<string, array<string, mixed>> $policySignatureMethods
	 * @return array<string, array<string, mixed>>
	 */
	private static function mergeSignatureMethods(array $catalogSignatureMethods, array $policySignatureMethods): array {
		$mergedSignatureMethods = [];

		if ($policySignatureMethods !== []) {
			// Policy explicitly declares which signature methods are active.
			// Enrich each declared method with catalog metadata (e.g. label) but do NOT
			// add catalog methods that the policy omitted.
			foreach (array_keys($policySignatureMethods) as $signatureMethodName) {
				$mergedSignatureMethods[$signatureMethodName] = array_merge(
					$catalogSignatureMethods[$signatureMethodName] ?? [],
					$policySignatureMethods[$signatureMethodName],
				);
			}
			return $mergedSignatureMethods;
		}

		// No explicit signature methods in policy – expose all catalog methods.
		foreach ($catalogSignatureMethods as $signatureMethodName => $catalogMethod) {
			$mergedSignatureMethods[$signatureMethodName] = $catalogMethod;
		}

		return $mergedSignatureMethods;
	}

	/**
	 * @return ?array<string, mixed>
	 */
	private static function normalizeLegacyStringEntry(string $entry, ?int $sharedMinimumTotalVerifiedFactors): ?array {
		$name = trim($entry);
		if ($name === '') {
			return null;
		}

		$normalizedEntry = [
			'name' => $name,
			'enabled' => true,
			'signatureMethods' => [],
		];

		if ($sharedMinimumTotalVerifiedFactors !== null) {
			$normalizedEntry['minimumTotalVerifiedFactors'] = $sharedMinimumTotalVerifiedFactors;
		}

		return $normalizedEntry;
	}

	/**
	 * @param array<string, mixed> $entry
	 * @return array{entry: array<string, mixed>, globalCanCreateAccount: ?bool}|null
	 */
	private static function normalizeFactorEntry(
		array $entry,
		?int $sharedMinimumTotalVerifiedFactors,
		?bool $globalCanCreateAccount,
	): ?array {
		$name = isset($entry['name']) && is_string($entry['name'])
			? trim($entry['name'])
			: '';
		if ($name === '') {
			return null;
		}

		$signatureMethods = self::normalizeSignatureMethods($entry);
		$normalizedEntry = [
			'name' => $name,
			'enabled' => array_key_exists('enabled', $entry) ? (bool)$entry['enabled'] : true,
			'signatureMethods' => $signatureMethods,
		];

		if (isset($entry['friendly_name']) && is_string($entry['friendly_name'])) {
			$normalizedEntry['friendly_name'] = $entry['friendly_name'];
		}

		$entryCanCreateAccount = null;
		if ($globalCanCreateAccount === null && array_key_exists('can_create_account', $entry)) {
			$entryCanCreateAccount = (bool)$entry['can_create_account'];
		}

		$minimumTotalVerifiedFactors = self::normalizeMinimumTotalVerifiedFactors($entry['minimumTotalVerifiedFactors'] ?? null)
			?? $sharedMinimumTotalVerifiedFactors;
		if ($minimumTotalVerifiedFactors !== null) {
			$normalizedEntry['minimumTotalVerifiedFactors'] = $minimumTotalVerifiedFactors;
		}

		$requirement = IdentifyMethodRequirement::tryFrom((string)($entry['requirement'] ?? ''));
		if ($requirement !== null) {
			$normalizedEntry['requirement'] = $requirement->value;
		}

		if (isset($entry['signatureMethodEnabled']) && is_string($entry['signatureMethodEnabled'])) {
			$normalizedEntry['signatureMethodEnabled'] = $entry['signatureMethodEnabled'];
		}

		return [
			'entry' => $normalizedEntry,
			'globalCanCreateAccount' => $entryCanCreateAccount,
		];
	}

	/**
	 * @param array<string, mixed> $entry
	 * @return array<string, array<string, mixed>>
	 */
	private static function normalizeSignatureMethods(array $entry): array {
		$signatureMethods = [];
		if (isset($entry['signatureMethods']) && is_array($entry['signatureMethods'])) {
			if (array_is_list($entry['signatureMethods'])) {
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
					$normalizedSignatureMethod = self::normalizeSignatureMethodConfig($signatureMethodConfig);
					if ($normalizedSignatureMethod !== null) {
						$signatureMethods[$signatureMethodName] = $normalizedSignatureMethod;
					}
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

		return $signatureMethods;
	}

	/**
	 * @return ?array<string, mixed>
	 */
	private static function normalizeSignatureMethodConfig(mixed $signatureMethodConfig): ?array {
		if (is_string($signatureMethodConfig)) {
			return [
				'enabled' => false,
				'label' => $signatureMethodConfig,
			];
		}

		if (!is_array($signatureMethodConfig)) {
			return null;
		}

		$normalizedSignatureMethod = [];
		$normalizedSignatureMethod = $signatureMethodConfig;
		if (array_key_exists('enabled', $signatureMethodConfig)) {
			$normalizedSignatureMethod['enabled'] = (bool)$signatureMethodConfig['enabled'];
		}

		if (isset($signatureMethodConfig['name']) && is_string($signatureMethodConfig['name']) && trim($signatureMethodConfig['name']) !== '') {
			$normalizedSignatureMethod['name'] = trim($signatureMethodConfig['name']);
		}

		if (isset($signatureMethodConfig['label']) && is_string($signatureMethodConfig['label']) && trim($signatureMethodConfig['label']) !== '') {
			$normalizedSignatureMethod['label'] = $signatureMethodConfig['label'];
		}

		if (!isset($normalizedSignatureMethod['name']) && isset($normalizedSignatureMethod['label']) && is_string($normalizedSignatureMethod['label'])) {
			$normalizedSignatureMethod['name'] = $normalizedSignatureMethod['label'];
		}

		return $normalizedSignatureMethod;
	}

	/**
	 * @param list<array<string, mixed>> $normalized
	 * @param array<string, string> $friendlyNames
	 * @return list<array<string, mixed>>
	 */
	private static function enrichFriendlyNames(array $normalized, array $friendlyNames): array {
		foreach ($normalized as &$entry) {
			if (!isset($entry['friendly_name']) && isset($entry['name'], $friendlyNames[$entry['name']])) {
				$entry['friendly_name'] = $friendlyNames[$entry['name']];
			}
		}
		unset($entry);

		return $normalized;
	}

	/**
	 * @param list<array<string, mixed>> $normalized
	 * @return list<array<string, mixed>>
	 */
	private static function canonicalizeFactorEntries(array $normalized): array {
		$canonicalized = [];
		foreach ($normalized as $entry) {
			$canonicalEntry = [];
			foreach (['name', 'enabled', 'signatureMethods', 'friendly_name', 'minimumTotalVerifiedFactors', 'requirement', 'signatureMethodEnabled'] as $key) {
				if (array_key_exists($key, $entry)) {
					$canonicalEntry[$key] = $entry[$key];
					unset($entry[$key]);
				}
			}

			foreach ($entry as $key => $value) {
				$canonicalEntry[$key] = $value;
			}

			$canonicalized[] = $canonicalEntry;
		}

		return $canonicalized;
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
