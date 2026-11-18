<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\OrganizationSeals;

/**
 * The policy value is the JSON list of the seal ids applied after the last
 * signature, in order. An empty list means no seal.
 */
final class OrganizationSealsPolicyValue {
	public const MAX_SEALS = 5;

	/** @return list<string> */
	public static function decode(mixed $rawValue): array {
		if (is_string($rawValue)) {
			$trimmed = trim($rawValue);
			if ($trimmed === '') {
				return [];
			}
			$rawValue = json_decode($trimmed, true);
		}

		if (!is_array($rawValue)) {
			return [];
		}

		$ids = [];
		foreach ($rawValue as $id) {
			if (!is_string($id)) {
				continue;
			}
			$id = trim($id);
			if ($id === '' || in_array($id, $ids, true)) {
				continue;
			}
			$ids[] = $id;
		}

		return $ids;
	}

	public static function encode(mixed $rawValue): string {
		return json_encode(self::decode($rawValue), JSON_THROW_ON_ERROR);
	}
}
