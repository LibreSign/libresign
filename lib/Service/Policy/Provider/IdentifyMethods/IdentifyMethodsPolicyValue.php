<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\IdentifyMethods;

final class IdentifyMethodsPolicyValue {
	/**
	 * @return list<array<string, mixed>>
	 */
	public static function normalize(mixed $rawValue): array {
		if (is_string($rawValue)) {
			$decoded = json_decode($rawValue, true);
			if (is_array($decoded)) {
				$rawValue = $decoded;
			}
		}

		if (!is_array($rawValue)) {
			return [];
		}

		$normalized = [];
		foreach ($rawValue as $entry) {
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
				foreach ($entry['signatureMethods'] as $signatureMethodName => $signatureMethodConfig) {
					if (!is_string($signatureMethodName) || trim($signatureMethodName) === '' || !is_array($signatureMethodConfig)) {
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

			$normalizedEntry = [
				'name' => $name,
				'enabled' => array_key_exists('enabled', $entry) ? (bool)$entry['enabled'] : true,
				'signatureMethods' => $signatureMethods,
			];

			if (isset($entry['friendly_name']) && is_string($entry['friendly_name'])) {
				$normalizedEntry['friendly_name'] = $entry['friendly_name'];
			}

			if (array_key_exists('can_create_account', $entry)) {
				$normalizedEntry['can_create_account'] = (bool)$entry['can_create_account'];
			}

			if (array_key_exists('mandatory', $entry)) {
				$normalizedEntry['mandatory'] = (bool)$entry['mandatory'];
			}

			if (isset($entry['signatureMethodEnabled']) && is_string($entry['signatureMethodEnabled'])) {
				$normalizedEntry['signatureMethodEnabled'] = $entry['signatureMethodEnabled'];
			}

			$normalized[] = $normalizedEntry;
		}

		return $normalized;
	}
}
