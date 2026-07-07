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
use OCA\Libresign\Service\Policy\Provider\Helper\DelegationLayerHelper;
use OCA\Libresign\Service\Policy\Provider\Helper\PolicyKeyNormalizer;

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

		return match (PolicyKeyNormalizer::normalize($policyKey)) {
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
				resolvedStateMeta: static fn (PolicyContext $_context): array => [
					'defaultSystemValue' => $resolvedStateDefault,
				],
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . PolicyKeyNormalizer::normalize($policyKey)),
		};
	}

	private function getDefaultTemplate(): string {
		if ($this->defaultTemplate !== null) {
			return $this->defaultTemplate;
		}

		return $this->defaultTemplate = (string)file_get_contents(FooterHandler::DEFAULT_TEMPLATE_PATH);
	}

	private static function canManageTechnicalFooterSettings(PolicyContext $context): bool {
		$actorRole = $context->getActorRole();

		return $actorRole->canManageSystemPolicies || $actorRole->canManageGroupPolicies;
	}
}
