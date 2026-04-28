<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\ApprovalGroups;

final class ApprovalGroupsPolicyValue {
	/** @var list<string> */
	public const DEFAULT_GROUPS = ['admin'];

	/** @return list<string> */
	public static function decode(mixed $rawValue): array {
		if (is_array($rawValue)) {
			return self::normalizeGroupIds($rawValue);
		}

		if (!is_string($rawValue)) {
			return [];
		}

		$trimmed = trim($rawValue);
		if ($trimmed === '') {
			return [];
		}

		$decoded = json_decode($trimmed, true);
		if (is_array($decoded)) {
			return self::normalizeGroupIds($decoded);
		}

		return self::normalizeGroupIds(array_map('trim', explode(',', $trimmed)));
	}

	public static function encode(mixed $rawValue): string {
		return json_encode(self::decode($rawValue), JSON_THROW_ON_ERROR);
	}

	/**
	 * @param array<mixed> $rawGroups
	 * @return list<string>
	 */
	private static function normalizeGroupIds(array $rawGroups): array {
		$normalized = [];
		foreach ($rawGroups as $groupId) {
			if (!is_string($groupId)) {
				continue;
			}

			$trimmed = trim($groupId);
			if ($trimmed === '') {
				continue;
			}

			$normalized[] = $trimmed;
		}

		$unique = array_values(array_unique($normalized));
		sort($unique);

		return $unique;
	}
}
