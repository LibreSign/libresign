<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignedCopyMail;

final class SignedCopyMailPolicyValue {
	public const MAX_ARCHIVE_ADDRESSES = 10;

	/** @return array{enabled: bool, archive_addresses: list<string>} */
	public static function defaults(): array {
		return [
			'enabled' => false,
			'archive_addresses' => [],
		];
	}

	/** @return array{enabled: bool, archive_addresses: list<string>} */
	public static function normalize(mixed $rawValue): array {
		if (is_string($rawValue)) {
			$trimmed = trim($rawValue);
			if ($trimmed !== '') {
				$decoded = json_decode($trimmed, true);
				if (is_array($decoded)) {
					$rawValue = $decoded;
				}
			}
		}

		if (!is_array($rawValue)) {
			return self::defaults();
		}

		return [
			'enabled' => filter_var($rawValue['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
			'archive_addresses' => self::normalizeAddresses($rawValue['archive_addresses'] ?? []),
		];
	}

	/** @param array<string, mixed> $value */
	public static function encode(array $value): string {
		return (string)json_encode(self::normalize($value), JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Keeps valid, unique addresses in their given order. Invalid entries
	 * are dropped instead of failing so a stored value can always be read.
	 *
	 * @return list<string>
	 */
	private static function normalizeAddresses(mixed $rawValue): array {
		if (is_string($rawValue)) {
			$rawValue = preg_split('/[\s,;]+/', $rawValue) ?: [];
		}
		if (!is_array($rawValue)) {
			return [];
		}

		$addresses = [];
		foreach ($rawValue as $candidate) {
			if (!is_string($candidate)) {
				continue;
			}
			$address = trim($candidate);
			if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
				continue;
			}
			$addresses[strtolower($address)] ??= $address;
			if (count($addresses) >= self::MAX_ARCHIVE_ADDRESSES) {
				break;
			}
		}
		return array_values($addresses);
	}
}
