<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureText;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\SignatureTextTemplate;
use OCP\IL10N;

final class SignatureTextPolicy implements IPolicyDefinitionProvider {
	public function __construct(
		private IL10N $l10n,
	) {
	}

	// Canonical consolidated key (signature stamp)
	public const KEY = 'signature_stamp';
	public const SYSTEM_APP_CONFIG_KEY = 'signature_text';

	#[\Override]
	public function keys(): array {
		return [
			self::KEY,
		];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		$normalizedKey = $this->normalizePolicyKey($policyKey);
		$defaultConsolidatedValue = $this->encodeConsolidatedValue($this->defaultConsolidatedValue());

		return match ($normalizedKey) {
			self::KEY => new PolicySpec(
				key: self::KEY,
				defaultSystemValue: $defaultConsolidatedValue,
				allowedValues: [],
				normalizer: fn (mixed $rawValue): string => $this->encodeConsolidatedValue(
					$this->normalizeConsolidatedValue($rawValue),
				),
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
				resolvedStateMeta: [
					'defaultSystemValue' => $defaultConsolidatedValue,
				],
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . $normalizedKey),
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

	/**
	 * @return array<string, mixed>
	 */
	private function defaultConsolidatedValue(): array {
		return [
			'template' => SignatureTextTemplate::translated($this->l10n, false),
			'template_font_size' => SignatureTextPolicyValue::DEFAULT_TEMPLATE_FONT_SIZE,
			'signature_font_size' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE,
			'signature_width' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_WIDTH,
			'signature_height' => SignatureTextPolicyValue::DEFAULT_SIGNATURE_HEIGHT,
			'background_type' => 'default',
			'render_mode' => 'default',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function normalizeConsolidatedValue(mixed $rawValue): array {
		$defaults = $this->defaultConsolidatedValue();

		if (is_string($rawValue)) {
			$decoded = json_decode($rawValue, true);
			if (is_array($decoded)) {
				$rawValue = $decoded;
			}
		}

		if (!is_array($rawValue)) {
			return $defaults;
		}

		$renderMode = (string)($rawValue['render_mode'] ?? $defaults['render_mode']);
		if (!in_array($renderMode, ['default', 'graphic', 'text', 'description_only'], true)) {
			$renderMode = 'default';
		}

		$backgroundType = $this->normalizeBackgroundType($rawValue['background_type'] ?? $defaults['background_type']);

		return [
			'template' => (string)($rawValue['template'] ?? $defaults['template']),
			'template_font_size' => max(0.1, (float)($rawValue['template_font_size'] ?? $defaults['template_font_size'])),
			'signature_font_size' => max(0.1, (float)($rawValue['signature_font_size'] ?? $defaults['signature_font_size'])),
			'signature_width' => max(0.1, (float)($rawValue['signature_width'] ?? $defaults['signature_width'])),
			'signature_height' => max(0.1, (float)($rawValue['signature_height'] ?? $defaults['signature_height'])),
			'background_type' => $backgroundType,
			'render_mode' => $renderMode,
		];
	}

	/**
	 * @param array<string, mixed> $value
	 */
	private function encodeConsolidatedValue(array $value): string {
		return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
	}

	private function normalizeBackgroundType(mixed $rawValue): string {
		if (!is_string($rawValue)) {
			return 'default';
		}

		$normalized = trim(strtolower($rawValue));
		if (in_array($normalized, ['default', 'custom', 'deleted'], true)) {
			return $normalized;
		}

		return 'default';
	}
}
