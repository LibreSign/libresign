<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Model;

use Closure;
use function in_array;
use function sprintf;

final class PolicySpec implements \OCA\Libresign\Service\Policy\Contract\IPolicyDefinition {
	public const RESOLUTION_MODE_RESOLVED = 'resolved';
	public const RESOLUTION_MODE_VALUE_CHOICE = 'value_choice';

	/** @var list<mixed>|Closure(PolicyContext): list<mixed> */
	private array|Closure $allowedValuesResolver;
	/** @var Closure(mixed): mixed|null */
	private ?Closure $normalizer;
	/** @var Closure(mixed, PolicyContext): void|null */
	private ?Closure $validator;
	/** @var array<string, mixed>|Closure(PolicyContext): array<string, mixed> */
	private array|Closure $resolvedStateMetaResolver;
	/** @var Closure(PolicyContext, ?PolicyLayer): bool|null */
	private ?Closure $visibleGroupCountFilterResolver;
	/** @var Closure(PolicyContext, ?PolicyLayer, array<array-key, PolicyLayer>): bool|null */
	private ?Closure $groupPolicyManagerResolver;
	/** @var Closure(PolicyContext, ?PolicyLayer, PolicyLayer): bool|null */
	private ?Closure $systemCreatedGroupRuleEditorResolver;
	/** @var Closure(mixed, mixed, PolicyContext): void|null */
	private ?Closure $delegatedValidator;

	/**
	 * @param list<mixed>|Closure(PolicyContext): list<mixed> $allowedValues
	 * @param Closure(mixed): mixed|null $normalizer
	 * @param Closure(mixed, PolicyContext): void|null $validator
	 * @param array<string, mixed>|Closure(PolicyContext): array<string, mixed> $resolvedStateMeta
	 * @param Closure(mixed, mixed, PolicyContext): void|null $delegatedValueValidator
	 * @param Closure(PolicyContext, ?PolicyLayer): bool|null $visibleGroupCountFilter
	 * @param Closure(PolicyContext, ?PolicyLayer, array<array-key, PolicyLayer>): bool|null $groupPolicyManager
	 * @param Closure(PolicyContext, ?PolicyLayer, PolicyLayer): bool|null $systemCreatedGroupRuleEditor
	 */
	public function __construct(
		private string $key,
		private mixed $defaultSystemValue,
		array|Closure $allowedValues,
		?Closure $normalizer = null,
		?Closure $validator = null,
		private ?string $appConfigKey = null,
		private string $resolutionMode = self::RESOLUTION_MODE_RESOLVED,
		private bool $supportsUserPreference = true,
		array|Closure $resolvedStateMeta = [],
		?Closure $visibleGroupCountFilter = null,
		?Closure $groupPolicyManager = null,
		?Closure $systemCreatedGroupRuleEditor = null,
		private bool $supportsGroupAdminDelegation = false,
		?Closure $delegatedValueValidator = null,
	) {
		$this->allowedValuesResolver = $allowedValues;
		$this->normalizer = $normalizer;
		$this->validator = $validator;
		$this->resolvedStateMetaResolver = $resolvedStateMeta;
		$this->visibleGroupCountFilterResolver = $visibleGroupCountFilter;
		$this->groupPolicyManagerResolver = $groupPolicyManager;
		$this->systemCreatedGroupRuleEditorResolver = $systemCreatedGroupRuleEditor;
		$this->delegatedValidator = $delegatedValueValidator;
	}

	#[\Override]
	public function key(): string {
		return $this->key;
	}

	#[\Override]
	public function resolutionMode(): string {
		return $this->resolutionMode;
	}

	#[\Override]
	public function getAppConfigKey(): string {
		return $this->appConfigKey ?? $this->key;
	}

	#[\Override]
	public function getUserPreferenceKey(): string {
		return 'policy.' . $this->key;
	}

	#[\Override]
	public function normalizeValue(mixed $rawValue): mixed {
		if ($this->normalizer !== null) {
			return ($this->normalizer)($rawValue);
		}

		return $rawValue;
	}

	#[\Override]
	public function validateValue(mixed $value, PolicyContext $context): void {
		if ($this->validator !== null) {
			($this->validator)($value, $context);
			return;
		}

		// Empty allowedValues means "no explicit restriction" for this policy key.
		if ($this->allowedValues($context) === []) {
			return;
		}

		if (!in_array($value, $this->allowedValues($context), true)) {
			throw new \InvalidArgumentException(sprintf('Invalid value for %s', $this->key()));
		}
	}

	#[\Override]
	public function allowedValues(PolicyContext $context): array {
		if ($this->allowedValuesResolver instanceof Closure) {
			return ($this->allowedValuesResolver)($context);
		}

		return $this->allowedValuesResolver;
	}

	#[\Override]
	public function defaultSystemValue(): mixed {
		return $this->defaultSystemValue;
	}

	/** @return array<string, mixed> */
	#[\Override]
	public function resolvedStateMeta(PolicyContext $context): array {
		if ($this->resolvedStateMetaResolver instanceof Closure) {
			return ($this->resolvedStateMetaResolver)($context);
		}

		return $this->resolvedStateMetaResolver;
	}

	#[\Override]
	public function supportsUserPreference(): bool {
		return $this->supportsUserPreference;
	}

	#[\Override]
	public function shouldFilterVisibleGroupCountsForActor(PolicyContext $context, ?PolicyLayer $systemPolicy): bool {
		if ($this->visibleGroupCountFilterResolver !== null) {
			return ($this->visibleGroupCountFilterResolver)($context, $systemPolicy);
		}

		if ($context->getActorRole()->canManageSystemPolicies) {
			return false;
		}

		return !$this->hasExplicitSystemGroupManagementDelegation($systemPolicy);
	}

	/** @param list<PolicyLayer> $groupLayers */
	#[\Override]
	public function canCurrentActorManageGroupPolicy(PolicyContext $context, ?PolicyLayer $systemPolicy, array $groupLayers): bool {
		if ($this->groupPolicyManagerResolver !== null) {
			return ($this->groupPolicyManagerResolver)($context, $systemPolicy, $groupLayers);
		}

		$actorRole = $context->getActorRole();
		if ($actorRole->canManageSystemPolicies) {
			return true;
		}

		if (!$actorRole->canManageGroupPolicies) {
			return false;
		}

		return $this->hasExplicitSystemGroupManagementDelegation($systemPolicy);
	}

	#[\Override]
	public function canCurrentActorEditSystemCreatedGroupPolicy(PolicyContext $context, ?PolicyLayer $systemPolicy, PolicyLayer $existingPolicy): bool {
		if ($this->systemCreatedGroupRuleEditorResolver !== null) {
			return ($this->systemCreatedGroupRuleEditorResolver)($context, $systemPolicy, $existingPolicy);
		}

		return true;
	}

	private function hasExplicitSystemGroupManagementDelegation(?PolicyLayer $systemPolicy): bool {
		return $systemPolicy instanceof PolicyLayer
			&& $systemPolicy->getScope() === 'global'
			&& $systemPolicy->isAllowChildOverride();
	}

	#[\Override]
	public function supportsGroupAdminDelegation(): bool {
		return $this->supportsGroupAdminDelegation;
	}

	#[\Override]
	public function validateGroupAdminDelegatedValue(
		mixed $proposedNormalizedValue,
		mixed $parentSeedNormalizedValue,
		PolicyContext $context,
	): void {
		if ($this->delegatedValidator !== null) {
			($this->delegatedValidator)($proposedNormalizedValue, $parentSeedNormalizedValue, $context);
		}
	}
}
