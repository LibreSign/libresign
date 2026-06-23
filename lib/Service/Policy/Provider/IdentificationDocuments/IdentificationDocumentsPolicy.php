<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\IdentificationDocuments;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class IdentificationDocumentsPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'identification_documents';
	public const SYSTEM_APP_CONFIG_KEY = 'identification_documents';

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
				defaultSystemValue: [
					'enabled' => false,
					'approvers' => ['admin'],
				],
				allowedValues: static fn (): array => [],
				normalizer: static fn (mixed $rawValue): array => \OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicyValue::normalize($rawValue, false),
				validator: static function (mixed $value): void {
					if (!is_array($value)) {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
					}
					if (!array_key_exists('enabled', $value)) {
						throw new \InvalidArgumentException('Missing "enabled" key in ' . self::KEY);
					}
					if (!array_key_exists('approvers', $value)) {
						throw new \InvalidArgumentException('Missing "approvers" key in ' . self::KEY);
					}
					if (!is_array($value['approvers'])) {
						throw new \InvalidArgumentException('Approvers must be an array');
					}
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
