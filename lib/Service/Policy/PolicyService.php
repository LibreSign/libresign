<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\Runtime\DefaultPolicyResolver;
use OCA\Libresign\Service\Policy\Runtime\PolicyContextFactory;
use OCA\Libresign\Service\Policy\Runtime\PolicyRegistry;
use OCA\Libresign\Service\Policy\Runtime\PolicySource;
use OCP\IL10N;
use OCP\IUser;

class PolicyService {
	private DefaultPolicyResolver $resolver;

	public function __construct(
		private PolicyContextFactory $contextFactory,
		private PolicySource $source,
		private PolicyRegistry $registry,
		private IL10N $l10n,
	) {
		$this->resolver = new DefaultPolicyResolver($this->source);
	}

	/** @param array<string, mixed> $requestOverrides */
	public function resolve(string|\BackedEnum $policyKey, array $requestOverrides = [], ?array $activeContext = null): ResolvedPolicy {
		return $this->resolver->resolve(
			$this->registry->get($policyKey),
			$this->contextFactory->forCurrentUser($requestOverrides, $activeContext),
		);
	}

	/** @param array<string, mixed> $requestOverrides */
	public function resolveForUserId(string|\BackedEnum $policyKey, ?string $userId, array $requestOverrides = [], ?array $activeContext = null): ResolvedPolicy {
		return $this->resolver->resolve(
			$this->registry->get($policyKey),
			$this->contextFactory->forUserId($userId, $requestOverrides, $activeContext),
		);
	}

	/** @param array<string, mixed> $requestOverrides */
	public function resolveForUser(string|\BackedEnum $policyKey, ?IUser $user, array $requestOverrides = [], ?array $activeContext = null): ResolvedPolicy {
		return $this->resolver->resolve(
			$this->registry->get($policyKey),
			$this->contextFactory->forUser($user, $requestOverrides, $activeContext),
		);
	}

	/** @return array<string, ResolvedPolicy> */
	public function resolveKnownPolicies(array $requestOverrides = [], ?array $activeContext = null): array {
		return $this->resolveKnownPoliciesForContext(
			$this->contextFactory->forCurrentUser($requestOverrides, $activeContext),
		);
	}

	/** @return array<string, ResolvedPolicy> */
	public function resolveKnownPoliciesForUserId(?string $userId, array $requestOverrides = [], ?array $activeContext = null): array {
		return $this->resolveKnownPoliciesForContext(
			$this->contextFactory->forUserId($userId, $requestOverrides, $activeContext),
		);
	}

	/** @return array<string, array<string, mixed>> */
	public function resolveKnownPolicyStates(array $requestOverrides = [], ?array $activeContext = null): array {
		return $this->serializeResolvedPolicies($this->resolveKnownPolicies($requestOverrides, $activeContext));
	}

	/** @return array<string, array<string, mixed>> */
	public function resolveKnownPolicyStatesForUserId(?string $userId, array $requestOverrides = [], ?array $activeContext = null): array {
		return $this->serializeResolvedPolicies($this->resolveKnownPoliciesForUserId($userId, $requestOverrides, $activeContext));
	}

	/**
	 * Resolve requester-facing policy states using the target user's group membership,
	 * while intentionally ignoring user-specific layers such as assigned user policies
	 * and personal preferences.
	 *
	 * This is used by public validation pages, which should reflect the requester's
	 * inherited group/system policy posture without exposing user-scoped overrides.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function resolveKnownPolicyStatesForUserIdWithoutUserScope(?string $userId, array $requestOverrides = [], ?array $activeContext = null): array {
		$context = $this->contextFactory->forUserId($userId, $requestOverrides, $activeContext);
		$context->setUserId(null);

		return $this->serializeResolvedPolicies(
			$this->resolveKnownPoliciesForContext($context),
		);
	}

	/**
	 * @param array<string, array{groupCount: int, userCount: int, everyoneCount: int}> $ruleCounts
	 * @return array<string, array<string, mixed>>
	 */
	public function resolveKnownPolicyStatesWithRuleCounts(array $ruleCounts, array $requestOverrides = [], ?array $activeContext = null): array {
		return $this->serializeResolvedPolicies(
			$this->resolveKnownPolicies($requestOverrides, $activeContext),
			$ruleCounts,
		);
	}

	public function getSystemPolicy(string|\BackedEnum $policyKey): ?PolicyLayer {
		$definition = $this->registry->get($policyKey);
		return $this->source->loadSystemPolicy($definition->key());
	}

	public function getUserPolicyForUserId(string|\BackedEnum $policyKey, string $userId): ?PolicyLayer {
		$definition = $this->registry->get($policyKey);
		if (!$definition->supportsScope(PolicySpec::SCOPE_USER)) {
			return null;
		}

		return $this->source->loadUserPolicyConfig($definition->key(), $userId);
	}

	/**
	 * @return list<array{targetId: string, policy: PolicyLayer}>
	 */
	public function listUserPolicies(string|\BackedEnum $policyKey): array {
		$definition = $this->registry->get($policyKey);
		if (!$definition->supportsScope(PolicySpec::SCOPE_USER)) {
			return [];
		}

		return $this->source->listUserPoliciesByKey($definition->key());
	}

	public function saveSystem(string|\BackedEnum $policyKey, mixed $value, bool $allowChildOverride = false): ResolvedPolicy {
		$context = $this->contextFactory->forCurrentUser();
		$definition = $this->registry->get($policyKey);
		$this->assertScopeSupported($definition, PolicySpec::SCOPE_SYSTEM);
		$normalizedValue = $value === null
			? $definition->normalizeValue($definition->defaultSystemValue())
			: $definition->normalizeValue($value);

		$definition->validateValue($normalizedValue, $context);
		$this->source->saveSystemPolicy($definition->key(), $normalizedValue, $allowChildOverride);

		return $this->resolver->resolve(
			$definition,
			$this->contextFactory->forUserId(null),
		);
	}

	public function clearSystem(string|\BackedEnum $policyKey): ResolvedPolicy {
		$definition = $this->registry->get($policyKey);
		$this->assertScopeSupported($definition, PolicySpec::SCOPE_SYSTEM);
		$this->source->clearSystemPolicy($definition->key());

		return $this->resolver->resolve(
			$definition,
			$this->contextFactory->forUserId(null),
		);
	}

	public function getGroupPolicy(string|\BackedEnum $policyKey, string $groupId): ?PolicyLayer {
		$definition = $this->registry->get($policyKey);
		if (!$definition->supportsScope(PolicySpec::SCOPE_GROUP)) {
			return null;
		}

		return $this->source->loadGroupPolicyConfig($definition->key(), $groupId);
	}

	/**
	 * @return list<array{targetId: string, policy: PolicyLayer}>
	 */
	public function listGroupPolicies(string|\BackedEnum $policyKey): array {
		$definition = $this->registry->get($policyKey);
		if (!$definition->supportsScope(PolicySpec::SCOPE_GROUP)) {
			return [];
		}

		return $this->source->listGroupPoliciesByKey($definition->key());
	}

	/**
	 * @param list<string> $groupIds
	 * @return list<array{targetId: string, policy: PolicyLayer}>
	 */
	public function listGroupPoliciesForTargets(string|\BackedEnum $policyKey, array $groupIds): array {
		$definition = $this->registry->get($policyKey);
		if (!$definition->supportsScope(PolicySpec::SCOPE_GROUP)) {
			return [];
		}

		return $this->source->listGroupPoliciesByKeyForTargets($definition->key(), $groupIds);
	}

	/**
	 * @param list<string> $groupIds
	 */
	public function countVisibleGroupPoliciesForTargets(string|\BackedEnum $policyKey, array $groupIds): int {
		$definition = $this->registry->get($policyKey);
		if (!$definition->supportsScope(PolicySpec::SCOPE_GROUP)) {
			return 0;
		}

		$visibleCount = 0;

		foreach ($this->source->listGroupPoliciesByKeyForTargets($definition->key(), $groupIds) as $record) {
			$groupId = (string)($record['targetId'] ?? '');
			$policy = $record['policy'] ?? null;
			if ($groupId === '' || !$policy instanceof PolicyLayer) {
				continue;
			}

			if (!$this->canViewGroupPolicy($definition->key(), $groupId, $policy)) {
				continue;
			}

			$visibleCount++;
		}

		return $visibleCount;
	}

	public function saveGroupPolicy(string|\BackedEnum $policyKey, string $groupId, mixed $value, bool $allowChildOverride): PolicyLayer {
		$definition = $this->registry->get($policyKey);
		$this->assertScopeSupported($definition, PolicySpec::SCOPE_GROUP);
		$context = $this->contextFactory->forCurrentUser();
		$this->assertCurrentActorCanManageGroupPolicy($definition->key(), $context);
		$this->assertCurrentActorCanEditGroupPolicy($definition->key(), $groupId, $context);
		$normalizedValue = $definition->normalizeValue($value);
		$definition->validateValue($normalizedValue, $context);
		$createdBySystemAdmin = $context->getActorRole()->canManageSystemPolicies;
		$this->source->saveGroupPolicy(
			$definition->key(),
			$groupId,
			$normalizedValue,
			$allowChildOverride,
			$createdBySystemAdmin,
			$context,
		);

		return $this->source->loadGroupPolicyConfig($definition->key(), $groupId)
			?? (new PolicyLayer())
				->setScope('group')
				->setVisibleToChild(true)
				->setAllowChildOverride(true)
				->setAllowedValues([]);
	}

	public function clearGroupPolicy(string|\BackedEnum $policyKey, string $groupId): ?PolicyLayer {
		$definition = $this->registry->get($policyKey);
		$this->assertScopeSupported($definition, PolicySpec::SCOPE_GROUP);
		$this->assertCurrentActorCanDeleteGroupPolicy($definition->key(), $groupId);
		$this->source->clearGroupPolicy(
			$definition->key(),
			$groupId,
			!$this->contextFactory->isCurrentActorSystemAdmin(),
		);

		return $this->source->loadGroupPolicyConfig($definition->key(), $groupId);
	}

	public function canDeleteGroupPolicy(string|\BackedEnum $policyKey, string $groupId, ?PolicyLayer $policy = null): bool {
		$definition = $this->registry->get($policyKey);
		if (!$definition->supportsScope(PolicySpec::SCOPE_GROUP)) {
			return false;
		}

		if ($this->contextFactory->isCurrentActorSystemAdmin()) {
			return true;
		}

		$groupPolicy = $policy ?? $this->source->loadGroupPolicyConfig($definition->key(), $groupId);
		if (!$groupPolicy instanceof PolicyLayer) {
			return false;
		}

		return !$groupPolicy->isCreatedBySystemAdmin();
	}

	public function canViewGroupPolicy(string|\BackedEnum $policyKey, string $groupId, ?PolicyLayer $policy = null): bool {
		$definition = $this->registry->get($policyKey);
		if (!$definition->supportsScope(PolicySpec::SCOPE_GROUP)) {
			return false;
		}

		if ($this->contextFactory->isCurrentActorSystemAdmin()) {
			return true;
		}

		$groupPolicy = $policy ?? $this->source->loadGroupPolicyConfig($definition->key(), $groupId);
		if (!$groupPolicy instanceof PolicyLayer) {
			return true;
		}

		return !$this->shouldHideSystemCreatedGroupRuleFromCurrentActor($definition->key(), $groupPolicy);
	}

	public function shouldFilterVisibleGroupCountsForCurrentActor(string|\BackedEnum $policyKey): bool {
		$definition = $this->registry->get($policyKey);
		return $definition->shouldFilterVisibleGroupCountsForActor(
			$this->contextFactory->forCurrentUser(),
			$this->source->loadSystemPolicy($definition->key()),
		);
	}

	public function canManageUserPolicyForUserId(string|\BackedEnum $policyKey, string $userId): bool {
		$definition = $this->registry->get($policyKey);
		if (!$definition->supportsScope(PolicySpec::SCOPE_USER)) {
			return false;
		}

		if ($this->contextFactory->isCurrentActorSystemAdmin()) {
			return true;
		}

		$resolved = $this->resolver->resolve(
			$definition,
			$this->contextFactory->forUserId($userId),
		);

		return $resolved->canSaveAsUserDefault()
			|| (($resolved->getMeta()['canCreateDescendantRules'] ?? false) === true);
	}

	private function assertCurrentActorCanDeleteGroupPolicy(string $policyKey, string $groupId): void {
		if ($this->canDeleteGroupPolicy($policyKey, $groupId)) {
			return;
		}

		throw new \DomainException($this->l10n->t('Only system administrators can delete group rules created by a system administrator'));
	}

	private function assertCurrentActorCanEditGroupPolicy(string $policyKey, string $groupId, ?PolicyContext $context = null): void {
		$context ??= $this->contextFactory->forCurrentUser();
		if ($context->getActorRole()->canManageSystemPolicies) {
			return;
		}

		$definition = $this->registry->get($policyKey);
		$existingPolicy = $this->source->loadGroupPolicyConfig($definition->key(), $groupId);
		if (!$existingPolicy instanceof PolicyLayer) {
			return;
		}

		if (!$this->wasGroupPolicyCreatedBySystemAdmin($existingPolicy)) {
			return;
		}

		if ($definition->canCurrentActorEditSystemCreatedGroupPolicy(
			$context,
			$this->source->loadSystemPolicy($definition->key()),
			$existingPolicy,
		)) {
			return;
		}

		throw new \DomainException($this->l10n->t('Only system administrators can edit group access rules created by a system administrator'));
	}

	private function wasGroupPolicyCreatedBySystemAdmin(PolicyLayer $policy): bool {
		return $policy->isCreatedBySystemAdmin();
	}

	private function shouldHideSystemCreatedGroupRuleFromCurrentActor(string $policyKey, PolicyLayer $policy): bool {
		if (!$this->wasGroupPolicyCreatedBySystemAdmin($policy)) {
			return false;
		}

		return $this->shouldFilterVisibleGroupCountsForCurrentActor($policyKey);
	}

	private function assertCurrentActorCanManageGroupPolicy(string $policyKey, ?PolicyContext $context = null): void {
		$context ??= $this->contextFactory->forCurrentUser();
		if ($context->getActorRole()->canManageSystemPolicies) {
			return;
		}

		$definition = $this->registry->get($policyKey);
		if (!$this->canCurrentActorManageGroupPolicy($definition, $context)) {
			throw new \DomainException($this->l10n->t('Group policy management requires explicit delegation from the system administrator'));
		}
	}

	private function canCurrentActorManageGroupPolicy(IPolicyDefinition $definition, PolicyContext $context): bool {
		return $definition->canCurrentActorManageGroupPolicy(
			$context,
			$this->source->loadSystemPolicy($definition->key()),
			$this->source->loadGroupPolicies($definition->key(), $context),
		);
	}

	private function assertScopeSupported(IPolicyDefinition $definition, string $scope): void {
		if ($definition->supportsScope($scope)) {
			return;
		}

		$scopeLabel = match ($scope) {
			PolicySpec::SCOPE_SYSTEM => 'System',
			PolicySpec::SCOPE_GROUP => 'Group',
			PolicySpec::SCOPE_USER => 'User',
			default => ucfirst($scope),
		};

		throw new \InvalidArgumentException($this->l10n->t('%s-level scope is not supported for this policy', [$scopeLabel]));
	}

	public function saveUserPreference(string|\BackedEnum $policyKey, mixed $value): ResolvedPolicy {
		$context = $this->contextFactory->forCurrentUser();
		$definition = $this->registry->get($policyKey);
		$this->assertScopeSupported($definition, PolicySpec::SCOPE_USER);
		$normalizedValue = $definition->normalizeValue($value);
		$definition->validateValue($normalizedValue, $context);
		$resolved = $this->resolver->resolve($definition, $context);
		if (!$resolved->canSaveAsUserDefault()) {
			throw new \InvalidArgumentException($this->l10n->t('Saving a user preference is not allowed for {policyKey}', [
				'policyKey' => $definition->key(),
			]));
		}

		$this->source->saveUserPreference($definition->key(), $context, $normalizedValue);

		return $this->resolver->resolve($definition, $context);
	}

	public function clearUserPreference(string|\BackedEnum $policyKey): ResolvedPolicy {
		$context = $this->contextFactory->forCurrentUser();
		$definition = $this->registry->get($policyKey);
		$this->assertScopeSupported($definition, PolicySpec::SCOPE_USER);
		$this->source->clearUserPreference($definition->key(), $context);

		return $this->resolver->resolve($definition, $context);
	}

	public function saveUserPolicyForUserId(string|\BackedEnum $policyKey, string $userId, mixed $value, bool $allowChildOverride): ?PolicyLayer {
		$context = $this->contextFactory->forUserId($userId);
		$definition = $this->registry->get($policyKey);
		$this->assertScopeSupported($definition, PolicySpec::SCOPE_USER);
		$normalizedValue = $definition->normalizeValue($value);
		$definition->validateValue($normalizedValue, $context);
		$this->source->saveUserPolicy($definition->key(), $context, $normalizedValue, $allowChildOverride);

		return $this->source->loadUserPolicy($definition->key(), $context)
			?? (new PolicyLayer())
				->setScope('user_policy')
				->setValue($normalizedValue)
				->setAllowChildOverride($allowChildOverride)
				->setVisibleToChild(true);
	}

	public function clearUserPolicyForUserId(string|\BackedEnum $policyKey, string $userId): ?PolicyLayer {
		$context = $this->contextFactory->forUserId($userId);
		$definition = $this->registry->get($policyKey);
		$this->assertScopeSupported($definition, PolicySpec::SCOPE_USER);
		$this->source->clearUserPolicy($definition->key(), $context);

		return $this->source->loadUserPolicy($definition->key(), $context);
	}

	/**
	 * @param list<string> $groupIds
	 * @param list<string> $userIds
	 * @return array<string, array{groupCount: int, userCount: int, everyoneCount: int}>
	 */
	public function getRuleCounts(array $groupIds, array $userIds): array {
		return $this->source->loadRuleCounts($groupIds, $userIds);
	}

	/** @return array<string, array{groupCount: int, userCount: int, everyoneCount: int}> */
	public function getAllRuleCounts(): array {
		return $this->source->loadAllRuleCounts();
	}

	/**
	 * @param array<string, ResolvedPolicy> $resolvedPolicies
	 * @param null|array<string, array{groupCount: int, userCount: int, everyoneCount: int}> $ruleCounts
	 * @return array<string, array<string, mixed>>
	 */
	private function serializeResolvedPolicies(array $resolvedPolicies, ?array $ruleCounts = null): array {
		$states = [];
		foreach ($resolvedPolicies as $policyKey => $resolvedPolicy) {
			$policyState = $resolvedPolicy->toArray();
			if ($ruleCounts !== null) {
				$policyState['groupCount'] = $ruleCounts[$policyKey]['groupCount'] ?? 0;
				$policyState['userCount'] = $ruleCounts[$policyKey]['userCount'] ?? 0;
				$policyState['everyoneCount'] = $ruleCounts[$policyKey]['everyoneCount'] ?? 0;
			}

			$states[$policyKey] = $policyState;
		}

		return $states;
	}

	/** @return array<string, ResolvedPolicy> */
	private function resolveKnownPoliciesForContext(PolicyContext $context): array {
		$definitions = [];
		foreach ($this->registry->getAllPolicyKeys() as $policyKey) {
			$definitions[] = $this->registry->get($policyKey);
		}

		return $this->resolver->resolveMany($definitions, $context);
	}
}
