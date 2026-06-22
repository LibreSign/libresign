<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Footer;

use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class FooterPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'add_footer';
	public const SYSTEM_APP_CONFIG_KEY = 'add_footer';
	private ?string $defaultTemplate = null;

	#[\Override]
	public function keys(): array {
		return [
			self::KEY,
		];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		$defaultTemplate = $this->getDefaultTemplate();
		$defaultSystemValue = FooterPolicyValue::encode(FooterPolicyValue::defaults());
		$resolvedStateDefault = FooterPolicyValue::encode(FooterPolicyValue::defaults($defaultTemplate), $defaultTemplate);

		return match ($this->normalizePolicyKey($policyKey)) {
			self::KEY => new PolicySpec(
				key: self::KEY,
				defaultSystemValue: $defaultSystemValue,
				allowedValues: static fn (): array => [],
				normalizer: static function (mixed $rawValue): mixed {
					return FooterPolicyValue::encode(FooterPolicyValue::normalize($rawValue));
				},
				validator: static function (mixed $value, PolicyContext $context): void {
					if (!is_string($value) || trim($value) === '') {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
					}

					$decoded = json_decode($value, true);
					if (!is_array($decoded)) {
						throw new \InvalidArgumentException('Invalid value for ' . self::KEY);
					}

					if (!self::canManageTechnicalFooterSettings($context)) {
						$normalized = FooterPolicyValue::normalize($decoded);
						if ($normalized['validationSite'] !== '') {
							throw new \InvalidArgumentException('Validation URL override is not allowed for this actor');
						}
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
				resolvedStateMeta: static fn (PolicyContext $_context): array => [
					'defaultSystemValue' => $resolvedStateDefault,
				],
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

	private function getDefaultTemplate(): string {
		if ($this->defaultTemplate !== null) {
			return $this->defaultTemplate;
		}

		return $this->defaultTemplate = (string)file_get_contents(FooterHandler::DEFAULT_TEMPLATE_PATH);
	}

	private function normalizePolicyKey(string|\BackedEnum $policyKey): string {
		if ($policyKey instanceof \BackedEnum) {
			return (string)$policyKey->value;
		}

		return $policyKey;
	}

	private static function canManageTechnicalFooterSettings(PolicyContext $context): bool {
		$actorRole = $context->getActorRole();

		return $actorRole->canManageSystemPolicies || $actorRole->canManageGroupPolicies;
	}
}
