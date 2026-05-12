<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\IdentificationDocuments;

final class IdentificationDocumentsPolicyValue {
	/** @var list<string> */
	private const TRUE_VALUES = ['1', 'true'];

	/** @var list<string> */
	private const FALSE_VALUES = ['0', 'false', ''];

	/** @var list<string> */
	private const DEFAULT_APPROVERS = ['admin'];

	/**
	 * Normalize mixed value to unified payload structure.
	 *
	 * @return array{enabled: bool, approvers: list<string>}
	 */
	public static function normalize(mixed $rawValue, bool $enabledDefault = false): array {
		$enabled = $enabledDefault;
		$approvers = self::DEFAULT_APPROVERS;

		// Extract enabled from various formats
		if (is_bool($rawValue)) {
			$enabled = $rawValue;
		} elseif (is_int($rawValue)) {
			$enabled = $rawValue === 1;
		} elseif (is_string($rawValue)) {
			$value = strtolower(trim($rawValue));
			$enabled = in_array($value, self::TRUE_VALUES, true);
		} elseif (is_array($rawValue)) {
			// Already structured payload
			$enabled = (bool) ($rawValue['enabled'] ?? $enabledDefault);
			if (isset($rawValue['approvers']) && is_array($rawValue['approvers'])) {
				$approvers = array_filter(
					array_map('strval', $rawValue['approvers']),
					static fn (string $v): bool => $v !== ''
				);
				if (!empty($approvers)) {
					$approvers = array_values($approvers);
				}
			}
		}

		return [
			'enabled' => $enabled,
			'approvers' => !empty($approvers) ? $approvers : self::DEFAULT_APPROVERS,
		];
	}

	/**
	 * Get enabled flag from normalized or raw value.
	 */
	public static function isEnabled(mixed $rawValue, bool $default = false): bool {
		$normalized = self::normalize($rawValue, $default);
		return $normalized['enabled'];
	}

	/**
	 * Get approvers from normalized or raw value.
	 *
	 * @return list<string>
	 */
	public static function getApprovers(mixed $rawValue): array {
		$normalized = self::normalize($rawValue);
		return $normalized['approvers'];
	}
}
