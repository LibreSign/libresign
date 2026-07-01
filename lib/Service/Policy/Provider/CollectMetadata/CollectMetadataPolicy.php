<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\CollectMetadata;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class CollectMetadataPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'collect_metadata';
	public const SYSTEM_APP_CONFIG_KEY = 'collect_metadata';

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
				defaultSystemValue: false,
				allowedValues: [
					false,
					true,
				],
				normalizer: static fn (mixed $rawValue): bool => filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY,
				supportedScopes: [
					PolicySpec::SCOPE_SYSTEM,
					PolicySpec::SCOPE_GROUP,
					PolicySpec::SCOPE_USER,
				],
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

					if (self::hasExplicitGlobalDelegation($systemPolicy)) {
						return true;
					}

					return self::hasSystemCreatedGroupDelegation($groupLayers);
				},
				systemCreatedGroupRuleEditor: static function (PolicyContext $context, ?PolicyLayer $systemPolicy, PolicyLayer $existingPolicy): bool {
					$actorRole = $context->getActorRole();

					if ($actorRole->canManageSystemPolicies) {
						return true;
					}

					if (!$actorRole->canManageGroupPolicies) {
						return false;
					}

					if (!$existingPolicy->isVisibleToChild()) {
						return false;
					}

					if (!$existingPolicy->isAllowChildOverride()) {
						return false;
					}

					if ($existingPolicy->getValue() === null) {
						return false;
					}

					if (self::hasExplicitGlobalDelegation($systemPolicy)) {
						return true;
					}

					return $existingPolicy->isCreatedBySystemAdmin();
				},
				supportsGroupAdminDelegation: true,
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

			if (!$groupLayer->isVisibleToChild()) {
				continue;
			}

			if ($groupLayer->getValue() === null) {
				continue;
			}

			if ($groupLayer->isDelegatedFromSystemCreatedSeed()) {
				return true;
			}

			if ($groupLayer->isAllowChildOverride()) {
				if ($groupLayer->isCreatedBySystemAdmin()) {
					return true;
				}
			}
		}

		return false;
	}

	private function normalizePolicyKey(string|\BackedEnum $policyKey): string {
		if ($policyKey instanceof \BackedEnum) {
			return (string)$policyKey->value;
		}

		return $policyKey;
	}
}
