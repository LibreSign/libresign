<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\IdentificationDocuments;

final class IdentificationDocumentsPolicyValue {
	/** @var list<string> */
	private const DEFAULT_APPROVERS = ['admin'];

	/**
	 * Normalize payload structure.
	 * Expected format: {enabled: bool, approvers: string[]}
	 *
	 * @return array{enabled: bool, approvers: list<string>}
	 */
	public static function normalize(mixed $rawValue, bool $enabledDefault = false): array {
		if (!is_array($rawValue)) {
			return [
				'enabled' => $enabledDefault,
				'approvers' => self::DEFAULT_APPROVERS,
			];
		}

		$enabled = (bool)($rawValue['enabled'] ?? $enabledDefault);
		$approvers = self::DEFAULT_APPROVERS;

		if (isset($rawValue['approvers']) && is_array($rawValue['approvers'])) {
			$filtered = array_filter(
				array_map('strval', $rawValue['approvers']),
				static fn (string $v): bool => $v !== ''
			);
			if (!empty($filtered)) {
				$approvers = array_values($filtered);
			}
		}

		return [
			'enabled' => $enabled,
			'approvers' => $approvers,
		];
	}

	/**
	 * Get enabled flag from payload.
	 */
	public static function isEnabled(mixed $rawValue, bool $default = false): bool {
		$normalized = self::normalize($rawValue, $default);
		return $normalized['enabled'];
	}

	/**
	 * Get approvers from payload.
	 *
	 * @return list<string>
	 */
	public static function getApprovers(mixed $rawValue): array {
		$normalized = self::normalize($rawValue);
		return $normalized['approvers'];
	}
}
