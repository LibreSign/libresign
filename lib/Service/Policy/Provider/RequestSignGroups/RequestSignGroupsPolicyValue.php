<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\RequestSignGroups;

final class RequestSignGroupsPolicyValue {
	/** @var list<string> */
	public const DEFAULT_ALLOW_GROUPS = ['admin'];

	/** @var list<string> */
	public const DEFAULT_DENY_GROUPS = [];

	/** @var list<string> Backward-compatible alias */
	public const DEFAULT_GROUPS = self::DEFAULT_ALLOW_GROUPS;

	/**
	 * @return array{allowGroups: list<string>, denyGroups: list<string>}
	 */
	public static function decodePolicy(mixed $rawValue): array {
		if (is_array($rawValue)) {
			if (self::isSequentialList($rawValue)) {
				return self::normalizePolicyPayload([
					'allowGroups' => $rawValue,
					'denyGroups' => [],
				]);
			}

			return self::normalizePolicyPayload($rawValue);
		}

		if (!is_string($rawValue)) {
			return self::normalizePolicyPayload([]);
		}

		$trimmed = trim($rawValue);
		if ($trimmed === '') {
			return self::normalizePolicyPayload([]);
		}

		$decoded = json_decode($trimmed, true);
		if (is_array($decoded)) {
			if (self::isSequentialList($decoded)) {
				return self::normalizePolicyPayload([
					'allowGroups' => $decoded,
					'denyGroups' => [],
				]);
			}

			return self::normalizePolicyPayload($decoded);
		}

		// Keep CSV fallback for legacy or manually edited values.
		return self::normalizePolicyPayload([
			'allowGroups' => array_map('trim', explode(',', $trimmed)),
			'denyGroups' => [],
		]);
	}

	/** @return list<string> */
	public static function decode(mixed $rawValue): array {
		return self::decodePolicy($rawValue)['allowGroups'];
	}

	/** @return list<string> */
	public static function decodeDenied(mixed $rawValue): array {
		return self::decodePolicy($rawValue)['denyGroups'];
	}

	/**
	 * Return all policy-scoped groups (allow + deny), normalized and deduplicated.
	 *
	 * @return list<string>
	 */
	public static function decodeScopedGroups(mixed $rawValue): array {
		$policy = self::decodePolicy($rawValue);

		return self::normalizeGroupIds([
			...$policy['allowGroups'],
			...$policy['denyGroups'],
		]);
	}

	/**
	 * Evaluate if at least one user group is authorized to request sign
	 * and none of the user groups are explicitly denied.
	 *
	 * @param array<mixed> $userGroups
	 */
	public static function canUserGroupsRequestSign(mixed $rawValue, array $userGroups): bool {
		$authorizedGroups = self::decode($rawValue);
		if ($authorizedGroups === []) {
			return false;
		}

		$normalizedUserGroups = self::normalizeGroupIds($userGroups);
		if ($normalizedUserGroups === []) {
			return false;
		}

		if (array_intersect($normalizedUserGroups, $authorizedGroups) === []) {
			return false;
		}

		return array_intersect($normalizedUserGroups, self::decodeDenied($rawValue)) === [];
	}

	public static function encode(mixed $rawValue): string {
		$payload = self::decodePolicy($rawValue);
		return json_encode($payload, JSON_THROW_ON_ERROR);
	}

	/** @param array<string, mixed> $rawValue */
	private static function normalizePolicyPayload(array $rawValue): array {
		$rawAllow = $rawValue['allowGroups'] ?? $rawValue['allow_groups'] ?? $rawValue['groups'] ?? self::DEFAULT_ALLOW_GROUPS;
		$rawDeny = $rawValue['denyGroups'] ?? $rawValue['deny_groups'] ?? $rawValue['deniedGroups'] ?? self::DEFAULT_DENY_GROUPS;

		$allowGroups = is_array($rawAllow)
			? self::normalizeGroupIds($rawAllow)
			: self::DEFAULT_ALLOW_GROUPS;
		$denyGroups = is_array($rawDeny)
			? self::normalizeGroupIds($rawDeny)
			: self::DEFAULT_DENY_GROUPS;

		return [
			'allowGroups' => $allowGroups,
			'denyGroups' => $denyGroups,
		];
	}

	/** @param array<mixed> $value */
	private static function isSequentialList(array $value): bool {
		return array_is_list($value);
	}

	/** @param array<mixed> $rawGroups
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
