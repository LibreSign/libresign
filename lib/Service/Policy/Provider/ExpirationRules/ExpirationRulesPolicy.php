<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\ExpirationRules;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class ExpirationRulesPolicy implements IPolicyDefinitionProvider {
	public const KEY_MAXIMUM_VALIDITY = 'maximum_validity';
	public const KEY_RENEWAL_INTERVAL = 'renewal_interval';
	public const KEY_EXPIRY_IN_DAYS = 'expiry_in_days';

	public const DEFAULT_MAXIMUM_VALIDITY = 0;
	public const DEFAULT_RENEWAL_INTERVAL = 0;
	public const DEFAULT_EXPIRY_IN_DAYS = 365;

	#[\Override]
	public function keys(): array {
		return [
			self::KEY_MAXIMUM_VALIDITY,
			self::KEY_RENEWAL_INTERVAL,
			self::KEY_EXPIRY_IN_DAYS,
		];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		$normalizedKey = $this->normalizePolicyKey($policyKey);

		return match ($normalizedKey) {
			self::KEY_MAXIMUM_VALIDITY => new PolicySpec(
				key: self::KEY_MAXIMUM_VALIDITY,
				defaultSystemValue: self::DEFAULT_MAXIMUM_VALIDITY,
				allowedValues: [],
				normalizer: static fn (mixed $rawValue): int => self::normalizeNonNegativeInt($rawValue, self::DEFAULT_MAXIMUM_VALIDITY),
				appConfigKey: self::KEY_MAXIMUM_VALIDITY,
			),
			self::KEY_RENEWAL_INTERVAL => new PolicySpec(
				key: self::KEY_RENEWAL_INTERVAL,
				defaultSystemValue: self::DEFAULT_RENEWAL_INTERVAL,
				allowedValues: [],
				normalizer: static fn (mixed $rawValue): int => self::normalizeNonNegativeInt($rawValue, self::DEFAULT_RENEWAL_INTERVAL),
				appConfigKey: self::KEY_RENEWAL_INTERVAL,
			),
			self::KEY_EXPIRY_IN_DAYS => new PolicySpec(
				key: self::KEY_EXPIRY_IN_DAYS,
				defaultSystemValue: self::DEFAULT_EXPIRY_IN_DAYS,
				allowedValues: [],
				normalizer: static fn (mixed $rawValue): int => self::normalizePositiveInt($rawValue, self::DEFAULT_EXPIRY_IN_DAYS),
				appConfigKey: self::KEY_EXPIRY_IN_DAYS,
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . $normalizedKey),
		};
	}

	private function normalizePolicyKey(string|\BackedEnum $policyKey): string {
		if ($policyKey instanceof \BackedEnum) {
			return (string)$policyKey->value;
		}

		return $policyKey;
	}

	private static function normalizeNonNegativeInt(mixed $rawValue, int $fallback): int {
		$parsed = self::parseInt($rawValue);
		if ($parsed === null) {
			return $fallback;
		}

		return max(0, $parsed);
	}

	private static function normalizePositiveInt(mixed $rawValue, int $fallback): int {
		$parsed = self::parseInt($rawValue);
		if ($parsed === null || $parsed <= 0) {
			return $fallback;
		}

		return $parsed;
	}

	private static function parseInt(mixed $rawValue): ?int {
		if (is_int($rawValue)) {
			return $rawValue;
		}

		if (is_float($rawValue) && is_finite($rawValue)) {
			return (int)$rawValue;
		}

		if (is_string($rawValue)) {
			$trimmed = trim($rawValue);
			if ($trimmed === '' || !is_numeric($trimmed)) {
				return null;
			}

			return (int)$trimmed;
		}

		return null;
	}
}
