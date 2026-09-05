<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureRejection;

use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\Helper\DelegationLayerHelper;
use OCA\Libresign\Service\Policy\Provider\Helper\PolicyKeyNormalizer;

final class SignatureRejectionPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'signature_rejection';
	public const SYSTEM_APP_CONFIG_KEY = 'signature_rejection';

	#[\Override]
	public function keys(): array {
		return [
			self::KEY,
		];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		return match (PolicyKeyNormalizer::normalize($policyKey)) {
			self::KEY => new PolicySpec(
				key: self::KEY,
				defaultSystemValue: SignatureRejectionPolicyValue::defaults(),
				allowedValues: static fn (): array => [],
				normalizer: static fn (mixed $rawValue): array => SignatureRejectionPolicyValue::normalize($rawValue),
				validator: static function (mixed $value): void {
					if (!is_array($value)) {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
					}
					if (!array_key_exists('enabled', $value) || !is_bool($value['enabled'])) {
						throw new \InvalidArgumentException('Missing "enabled" key in ' . self::KEY);
					}
					if (SignatureRejectionCommentMode::tryFrom((string)($value['comment_mode'] ?? '')) === null) {
						throw new \InvalidArgumentException('Invalid comment mode in ' . self::KEY);
					}
				},
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY,
				supportedScopes: [
					PolicySpec::SCOPE_SYSTEM,
					PolicySpec::SCOPE_GROUP,
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

					if (DelegationLayerHelper::hasExplicitGlobalDelegation($systemPolicy)) {
						return true;
					}

					return DelegationLayerHelper::hasSystemCreatedGroupDelegation($groupLayers);
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
					$parent = SignatureRejectionPolicyValue::normalize($parentSeedNormalizedValue);
					if (SignatureRejectionPolicyValue::getCommentMode($parent) !== SignatureRejectionCommentMode::REQUIRED) {
						return;
					}

					$proposed = SignatureRejectionPolicyValue::normalize($proposedNormalizedValue);
					if (SignatureRejectionPolicyValue::getCommentMode($proposed) === SignatureRejectionCommentMode::REQUIRED) {
						return;
					}

					throw new \InvalidArgumentException(
						'A delegated rule cannot make the rejection comment optional when it is required by a parent policy',
					);
				},
				supportsUserPreference: false,
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . PolicyKeyNormalizer::normalize($policyKey)),
		};
	}
}
