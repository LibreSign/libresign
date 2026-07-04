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
use OCA\Libresign\Service\Policy\Provider\Helper\DelegationLayerHelper;
use OCA\Libresign\Service\Policy\Provider\Helper\PolicyKeyNormalizer;

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
		return match (PolicyKeyNormalizer::normalize($policyKey)) {
			self::KEY => new PolicySpec(
				key: self::KEY,
				defaultSystemValue: 'SHA256',
				allowedValues: self::ALGORITHMS,
				normalizer: static fn (mixed $rawValue): string => strtoupper(trim((string)$rawValue)),
				validator: static function (mixed $value): void {
					if (!is_string($value) || !in_array($value, self::ALGORITHMS, true)) {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
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
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . PolicyKeyNormalizer::normalize($policyKey)),
		};
	}

}
