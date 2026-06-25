<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\ExpirationRules;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
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
			self::KEY_MAXIMUM_VALIDITY => $this->buildDelegableNonNegativeIntPolicy(
				self::KEY_MAXIMUM_VALIDITY,
				self::DEFAULT_MAXIMUM_VALIDITY,
			),
			self::KEY_RENEWAL_INTERVAL => $this->buildDelegableNonNegativeIntPolicy(
				self::KEY_RENEWAL_INTERVAL,
				self::DEFAULT_RENEWAL_INTERVAL,
			),
			self::KEY_EXPIRY_IN_DAYS => $this->buildDelegablePositiveIntPolicy(
				self::KEY_EXPIRY_IN_DAYS,
				self::DEFAULT_EXPIRY_IN_DAYS,
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . $normalizedKey),
		};
	}

	private function buildDelegableNonNegativeIntPolicy(string $key, int $defaultValue): PolicySpec {
		return $this->buildDelegableIntPolicy(
			key: $key,
			defaultValue: $defaultValue,
			normalizer: static fn (mixed $rawValue): int => self::normalizeNonNegativeInt($rawValue, $defaultValue),
			supportsUserPreference: false,
		);
	}

	private function buildDelegablePositiveIntPolicy(string $key, int $defaultValue): PolicySpec {
		return $this->buildDelegableIntPolicy(
			key: $key,
			defaultValue: $defaultValue,
			normalizer: static fn (mixed $rawValue): int => self::normalizePositiveInt($rawValue, $defaultValue),
		);
	}

	private function buildDelegableIntPolicy(
		string $key,
		int $defaultValue,
		\Closure $normalizer,
		bool $supportsUserPreference = true,
	): PolicySpec {
		return new PolicySpec(
			key: $key,
			defaultSystemValue: $defaultValue,
			allowedValues: [],
			normalizer: $normalizer,
			appConfigKey: $key,
			supportsUserPreference: $supportsUserPreference,
			groupPolicyManager: static function (PolicyContext $context, ?PolicyLayer $systemPolicy, array $groupLayers): bool {
				$actorRole = $context->getActorRole();
				if ($actorRole->canManageSystemPolicies) {
					return true;
				}

				if (!$actorRole->canManageGroupPolicies) {
					return false;
				}

				if ($actorRole->manageableGroupCount < 1) {
					return false;
				}

				return self::hasExplicitGlobalDelegation($systemPolicy)
					|| self::hasSystemCreatedGroupDelegation($groupLayers);
			},
			systemCreatedGroupRuleEditor: static function (PolicyContext $context, ?PolicyLayer $systemPolicy, PolicyLayer $existingPolicy): bool {
				$actorRole = $context->getActorRole();
				if ($actorRole->canManageSystemPolicies) {
					return true;
				}

				if (!$actorRole->canManageGroupPolicies) {
					return false;
				}

				if (!$existingPolicy->isVisibleToChild() || !$existingPolicy->isAllowChildOverride() || $existingPolicy->getValue() === null) {
					return false;
				}

				if (self::hasExplicitGlobalDelegation($systemPolicy)) {
					return true;
				}

				return self::wasCreatedBySystemAdmin($existingPolicy);
			},
			supportsGroupAdminDelegation: true,
		);
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

	private static function hasExplicitGlobalDelegation(?PolicyLayer $systemPolicy): bool {
		return $systemPolicy instanceof PolicyLayer
			&& $systemPolicy->getScope() === 'global'
			&& $systemPolicy->isVisibleToChild()
			&& $systemPolicy->isAllowChildOverride()
			&& $systemPolicy->getValue() !== null;
	}

	/** @param array<array-key, PolicyLayer> $groupLayers */
	private static function hasSystemCreatedGroupDelegation(array $groupLayers): bool {
		foreach ($groupLayers as $groupLayer) {
			if (!$groupLayer instanceof PolicyLayer) {
				continue;
			}

			if (!$groupLayer->isVisibleToChild() || $groupLayer->getValue() === null) {
				continue;
			}

			if (self::isDelegatedFromSystemCreatedSeed($groupLayer)) {
				return true;
			}

			if ($groupLayer->isAllowChildOverride() && self::wasCreatedBySystemAdmin($groupLayer)) {
				return true;
			}
		}

		return false;
	}

	private static function isDelegatedFromSystemCreatedSeed(PolicyLayer $policy): bool {
		return $policy->isDelegatedFromSystemCreatedSeed();
	}

	private static function wasCreatedBySystemAdmin(PolicyLayer $policy): bool {
		return $policy->isCreatedBySystemAdmin();
	}
}
