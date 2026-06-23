<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureHashAlgorithm;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class SignatureHashAlgorithmPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'signature_hash_algorithm';
	public const SYSTEM_APP_CONFIG_KEY = 'signature_hash_algorithm';

	/** @var string[] */
	private const ALGORITHMS = [
		'SHA1',
		'SHA256',
		'SHA384',
		'SHA512',
		'RIPEMD160',
	];

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
				defaultSystemValue: 'SHA256',
				allowedValues: self::ALGORITHMS,
				normalizer: function (mixed $rawValue): string {
					$candidate = strtoupper(trim((string)$rawValue));
					return in_array($candidate, self::ALGORITHMS, true) ? $candidate : 'SHA256';
				},
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY,
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
