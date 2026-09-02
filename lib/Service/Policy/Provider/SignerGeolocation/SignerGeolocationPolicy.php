<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignerGeolocation;

use OCA\Libresign\Enum\SignerGeolocationMode;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\Helper\DelegationLayerHelper;
use OCA\Libresign\Service\Policy\Provider\Helper\PolicyKeyNormalizer;

final class SignerGeolocationPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'signer_geolocation';
	public const SYSTEM_APP_CONFIG_KEY = 'signer_geolocation';

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
				defaultSystemValue: SignerGeolocationPolicyValue::defaults(),
				allowedValues: static fn (): array => [],
				normalizer: static fn (mixed $rawValue): array => SignerGeolocationPolicyValue::normalize($rawValue),
				validator: static function (mixed $value): void {
					if (!is_array($value)) {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
					}
					if (!array_key_exists('mode', $value)) {
						throw new \InvalidArgumentException('Missing "mode" key in ' . self::KEY);
					}
					if (SignerGeolocationMode::tryFrom((string)$value['mode']) === null) {
						throw new \InvalidArgumentException('Invalid mode in ' . self::KEY);
					}
				},
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

					if (!$existingPolicy->isVisibleToChild()) {
						return false;
					}

					if (!$existingPolicy->isAllowChildOverride()) {
						return false;
					}

					if ($existingPolicy->getValue() === null) {
						return false;
					}

					if (DelegationLayerHelper::hasExplicitGlobalDelegation($systemPolicy)) {
						return true;
					}

					return $existingPolicy->isCreatedBySystemAdmin();
				},
				supportsGroupAdminDelegation: true,
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . PolicyKeyNormalizer::normalize($policyKey)),
		};
	}
}
