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
use OCA\Libresign\Service\Policy\Provider\Helper\DelegationLayerHelper;
use OCA\Libresign\Service\Policy\Provider\Helper\PolicyKeyNormalizer;

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
		$normalizedKey = PolicyKeyNormalizer::normalize($policyKey);

		return match ($normalizedKey) {
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
				supportedScopes: [PolicySpec::SCOPE_SYSTEM, PolicySpec::SCOPE_GROUP],
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
				default => throw new \InvalidArgumentException('Unknown policy key: ' . $normalizedKey),
		};
	}
}
