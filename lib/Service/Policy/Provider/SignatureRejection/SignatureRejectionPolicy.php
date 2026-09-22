<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\SignatureRejection;

use OCA\Libresign\Enum\SignatureRejectionBehavior;
use OCA\Libresign\Enum\SignatureRejectionCommentMode;
use OCA\Libresign\Enum\SignatureRejectionVisibility;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\Helper\DelegationLayerHelper;
use OCA\Libresign\Service\Policy\Provider\Helper\PolicyKeyNormalizer;

/**
 * The rules that govern how a signer may refuse to sign.
 *
 * Every setting is its own scalar policy key, so an administrator can enforce
 * one of them and still let the requester choose the others: each key carries
 * its own allowed values and its own default, and the requester picks inside
 * what the layers above allow. The keys are grouped under `rejection_enabled`,
 * which is the setting the others depend on, the way the expiration rules are
 * grouped under the maximum validity.
 *
 * Rules that span more than one key cannot live in a single key's validator, so
 * the framework hands the whole family to {@see SignatureRejectionPolicyValidator}
 * before any of it is written: the compound write path passes the final combined
 * configuration to the parent key, and a signature request is validated the same
 * way. The outcome therefore never depends on the order in which the keys are
 * saved, and no part of the framework needs to know how the settings relate to
 * each other.
 */
final class SignatureRejectionPolicy implements IPolicyDefinitionProvider {
	public const KEY_ENABLED = 'rejection_enabled';
	public const KEY_BEHAVIOR = 'rejection_behavior';
	public const KEY_COMMENT_MODE = 'rejection_comment_mode';
	public const KEY_VISIBILITY = 'rejection_visibility';
	public const KEY_COMMENT_VISIBILITY = 'rejection_comment_visibility';

	/** The settings that only apply while rejection is enabled. */
	public const DEPENDENT_KEYS = [
		self::KEY_BEHAVIOR,
		self::KEY_COMMENT_MODE,
		self::KEY_VISIBILITY,
		self::KEY_COMMENT_VISIBILITY,
	];

	public const ALL_KEYS = [
		self::KEY_ENABLED,
		self::KEY_BEHAVIOR,
		self::KEY_COMMENT_MODE,
		self::KEY_VISIBILITY,
		self::KEY_COMMENT_VISIBILITY,
	];

	public function __construct(
		private SignatureRejectionPolicyValidator $rejectionPolicyValidator,
	) {
	}

	#[\Override]
	public function keys(): array {
		return self::ALL_KEYS;
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		$normalizedKey = PolicyKeyNormalizer::normalize($policyKey);

		return match ($normalizedKey) {
			self::KEY_ENABLED => $this->buildSpec(
				key: self::KEY_ENABLED,
				defaultValue: SignatureRejectionPolicyConfig::DEFAULT_ENABLED,
				allowedValues: [false, true],
				normalizer: static fn (mixed $rawValue): bool => SignatureRejectionPolicyConfig::normalizeEnabled($rawValue),
				compositeChildren: self::DEPENDENT_KEYS,
				compositeValidator: $this->combinedConfigurationValidator(),
			),
			self::KEY_BEHAVIOR => $this->buildSpec(
				key: self::KEY_BEHAVIOR,
				defaultValue: SignatureRejectionPolicyConfig::DEFAULT_BEHAVIOR->value,
				allowedValues: array_column(SignatureRejectionBehavior::cases(), 'value'),
				normalizer: static fn (mixed $rawValue): string => SignatureRejectionPolicyConfig::normalizeBehavior($rawValue)->value,
				parentPolicyKey: self::KEY_ENABLED,
			),
			self::KEY_COMMENT_MODE => $this->buildSpec(
				key: self::KEY_COMMENT_MODE,
				defaultValue: SignatureRejectionPolicyConfig::DEFAULT_COMMENT_MODE->value,
				allowedValues: array_column(SignatureRejectionCommentMode::cases(), 'value'),
				normalizer: static fn (mixed $rawValue): string => SignatureRejectionPolicyConfig::normalizeCommentMode($rawValue)->value,
				parentPolicyKey: self::KEY_ENABLED,
				delegatedValueValidator: $this->commentModeDelegationValidator(),
			),
			self::KEY_VISIBILITY => $this->buildSpec(
				key: self::KEY_VISIBILITY,
				defaultValue: SignatureRejectionPolicyConfig::DEFAULT_VISIBILITY->value,
				allowedValues: array_column(SignatureRejectionVisibility::cases(), 'value'),
				normalizer: $this->visibilityNormalizer(SignatureRejectionPolicyConfig::DEFAULT_VISIBILITY),
				parentPolicyKey: self::KEY_ENABLED,
				delegatedValueValidator: $this->audienceDelegationValidator(SignatureRejectionPolicyConfig::DEFAULT_VISIBILITY),
			),
			self::KEY_COMMENT_VISIBILITY => $this->buildSpec(
				key: self::KEY_COMMENT_VISIBILITY,
				defaultValue: SignatureRejectionPolicyConfig::DEFAULT_COMMENT_VISIBILITY->value,
				allowedValues: array_column(SignatureRejectionVisibility::cases(), 'value'),
				normalizer: $this->visibilityNormalizer(SignatureRejectionPolicyConfig::DEFAULT_COMMENT_VISIBILITY),
				parentPolicyKey: self::KEY_ENABLED,
				delegatedValueValidator: $this->audienceDelegationValidator(SignatureRejectionPolicyConfig::DEFAULT_COMMENT_VISIBILITY),
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . $normalizedKey),
		};
	}

	/**
	 * @param list<mixed> $allowedValues
	 * @param list<string> $compositeChildren
	 */
	private function buildSpec(
		string $key,
		mixed $defaultValue,
		array $allowedValues,
		\Closure $normalizer,
		array $compositeChildren = [],
		?string $parentPolicyKey = null,
		?\Closure $delegatedValueValidator = null,
		?\Closure $compositeValidator = null,
	): PolicySpec {
		return new PolicySpec(
			key: $key,
			defaultSystemValue: $defaultValue,
			allowedValues: $allowedValues,
			normalizer: $normalizer,
			appConfigKey: $key,
			resolutionMode: PolicySpec::RESOLUTION_MODE_VALUE_CHOICE,
			// The user scope is what lets a requester decide, per signature request,
			// how rejection behaves on their document. It is never read from the
			// signer: the effective value always comes from the snapshot frozen on
			// the document in the context of whoever requested the signature.
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
			delegatedValueValidator: $delegatedValueValidator,
			// Dependent settings are edited as part of the rejection policy, never
			// as a rule of their own, so the workbench shows them under the setting
			// they belong to.
			helper: $parentPolicyKey !== null,
			parentPolicyKey: $parentPolicyKey,
			compositeChildren: $compositeChildren,
			compositeValidator: $compositeValidator,
		);
	}

	/**
	 * The five settings are only meaningful together, so what is validated is
	 * the configuration the write produces, never a single key.
	 */
	private function combinedConfigurationValidator(): \Closure {
		return function (array $normalizedValues, array $submittedKeys): void {
			$this->rejectionPolicyValidator->validateLayer($normalizedValues, $submittedKeys);
		};
	}

	private function visibilityNormalizer(SignatureRejectionVisibility $fallback): \Closure {
		return static fn (mixed $rawValue): string
			=> SignatureRejectionPolicyConfig::normalizeVisibility($rawValue, $fallback)->value;
	}

	/**
	 * A delegated rule may tighten what the layer above defined, never loosen it:
	 * a comment the parent requires stays required.
	 */
	private function commentModeDelegationValidator(): \Closure {
		return static function (mixed $proposedNormalizedValue, mixed $parentSeedNormalizedValue): void {
			$parent = SignatureRejectionPolicyConfig::normalizeCommentMode($parentSeedNormalizedValue);
			if ($parent !== SignatureRejectionCommentMode::REQUIRED) {
				return;
			}

			$proposed = SignatureRejectionPolicyConfig::normalizeCommentMode($proposedNormalizedValue);
			if ($proposed === SignatureRejectionCommentMode::REQUIRED) {
				return;
			}

			throw new \InvalidArgumentException(
				'A delegated rule cannot make the rejection comment optional when it is required by a parent policy',
			);
		};
	}

	/**
	 * The same principle applied to an audience: a delegated rule may narrow the
	 * audience defined above, never widen it.
	 */
	private function audienceDelegationValidator(SignatureRejectionVisibility $fallback): \Closure {
		return static function (mixed $proposedNormalizedValue, mixed $parentSeedNormalizedValue) use ($fallback): void {
			$parent = SignatureRejectionPolicyConfig::normalizeVisibility($parentSeedNormalizedValue, $fallback);
			$proposed = SignatureRejectionPolicyConfig::normalizeVisibility($proposedNormalizedValue, $fallback);
			if ($parent->covers($proposed)) {
				return;
			}

			throw new \InvalidArgumentException(
				'A delegated rule cannot disclose a rejection to a wider audience than the parent policy allows',
			);
		};
	}
}
