<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\RequestSignGroups;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class RequestSignGroupsPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'groups_request_sign';
	public const SYSTEM_APP_CONFIG_KEY = self::KEY;

	#[\Override]
	public function keys(): array {
		return [
			self::KEY,
		];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		return match ($this->normalizePolicyKey($policyKey)) {
			self::KEY => new PolicySpec(
				key: self::KEY,
				defaultSystemValue: RequestSignGroupsPolicyValue::encode([
					'allowGroups' => RequestSignGroupsPolicyValue::DEFAULT_ALLOW_GROUPS,
					'denyGroups' => RequestSignGroupsPolicyValue::DEFAULT_DENY_GROUPS,
				]),
				allowedValues: static fn (PolicyContext $context): array => [],
				normalizer: static fn (mixed $rawValue): mixed => RequestSignGroupsPolicyValue::encode($rawValue),
				validator: static function (mixed $value): void {
					if (!is_string($value)) {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
					}

					$decoded = RequestSignGroupsPolicyValue::decodePolicy($value);
					if ($decoded['allowGroups'] === []) {
						throw new \InvalidArgumentException('At least one authorized group is required for ' . self::KEY);
					}
				},
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY,
				supportsUserPreference: false,
				visibleGroupCountFilter: static function (PolicyContext $context, ?PolicyLayer $systemPolicy): bool {
					return !$context->getActorRole()->canManageSystemPolicies;
				},
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
				delegatedValueValidator: static function (mixed $proposedNormalizedValue): void {
					if (!is_string($proposedNormalizedValue)) {
						return;
					}

					$decoded = RequestSignGroupsPolicyValue::decodePolicy($proposedNormalizedValue);
					if ($decoded['denyGroups'] === []) {
						throw new \InvalidArgumentException('This group is already authorized by a system administrator. Add a deny rule to override it.');
					}
				},
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . $this->normalizePolicyKey($policyKey)),
		};
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

	private function normalizePolicyKey(string|\BackedEnum $policyKey): string {
		if ($policyKey instanceof \BackedEnum) {
			return (string)$policyKey->value;
		}

		return $policyKey;
	}
}
