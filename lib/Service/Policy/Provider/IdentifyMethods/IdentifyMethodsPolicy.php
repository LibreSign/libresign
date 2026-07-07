<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\IdentifyMethods;

use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\Helper\DelegationLayerHelper;
use OCA\Libresign\Service\Policy\Provider\Helper\PolicyKeyNormalizer;

final class IdentifyMethodsPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'identify_methods';
	public const SYSTEM_APP_CONFIG_KEY = self::KEY;

	public function __construct(
		private IdentifyMethodService $identifyMethodService,
	) {
	}

	#[\Override]
	public function keys(): array {
		return [
			self::KEY,
		];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		$identifyMethodService = $this->identifyMethodService;
		return match (PolicyKeyNormalizer::normalize($policyKey)) {
			self::KEY => new PolicySpec(
				key: self::KEY,
				defaultSystemValue: [
					'factors' => [],
				],
				allowedValues: static fn (): array => [],
				normalizer: fn (mixed $rawValue): array => IdentifyMethodsPolicyValue::normalize($rawValue, $identifyMethodService),
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

					return DelegationLayerHelper::hasExplicitGlobalDelegation($systemPolicy)
						|| DelegationLayerHelper::hasSystemCreatedGroupDelegation($groupLayers);
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

					if (DelegationLayerHelper::hasExplicitGlobalDelegation($systemPolicy)) {
						return true;
					}

					return $existingPolicy->isCreatedBySystemAdmin();
				},
				supportsGroupAdminDelegation: true,
				delegatedValueValidator: static function (mixed $proposedNormalizedValue, mixed $parentSeedNormalizedValue): void {
					self::assertDelegatedOverrideOnlyEnablesGrantedFactors($proposedNormalizedValue, $parentSeedNormalizedValue);
				},
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . PolicyKeyNormalizer::normalize($policyKey)),
		};
	}

	private static function assertDelegatedOverrideOnlyEnablesGrantedFactors(mixed $proposedNormalizedValue, mixed $parentSeedNormalizedValue): void {
		$parentEnabledFactorNames = self::extractEnabledFactorNames($parentSeedNormalizedValue);
		$proposedEnabledFactorNames = self::extractEnabledFactorNames($proposedNormalizedValue);

		$invalidFactorNames = array_values(array_diff($proposedEnabledFactorNames, $parentEnabledFactorNames));
		if ($invalidFactorNames !== []) {
			throw new \InvalidArgumentException('Delegated identify methods overrides can only enable factors already granted by the system administrator.');
		}
	}

	/** @return list<string> */
	private static function extractEnabledFactorNames(mixed $normalizedValue): array {
		if (!is_array($normalizedValue)) {
			return [];
		}

		$enabledFactorNames = [];
		foreach (IdentifyMethodsPolicyValue::extractFactors($normalizedValue) as $factor) {
			$name = isset($factor['name']) && is_string($factor['name'])
				? trim($factor['name'])
				: '';
			if ($name === '' || !($factor['enabled'] ?? false)) {
				continue;
			}

			$enabledFactorNames[] = $name;
		}

		return array_values(array_unique($enabledFactorNames));
	}

}
