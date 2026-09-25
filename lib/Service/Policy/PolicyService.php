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
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;

class PolicyService {
	private DefaultPolicyResolver $resolver;

	public function __construct(
		private PolicyContextFactory $contextFactory,
		private PolicySource $source,
		private PolicyRegistry $registry,
		private IL10N $l10n,
		private IDBConnection $db,
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

		$definition->validateValueForPersistence($normalizedValue, $context);
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
		$definition->validateValueForPersistence($normalizedValue, $context);
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

		// TRANSLATORS Permission error shown when a non-admin tries to delete a group policy rule created by a system administrator.
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

		// TRANSLATORS Permission error shown when a non-admin tries to edit a group access rule created by a system administrator.
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
			// TRANSLATORS Permission error shown when managing group policies without an explicit delegation from a system administrator.
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

		// TRANSLATORS Error shown when saving a LibreSign policy with an unsupported scope. %s is the scope label such as user or group.
		throw new \InvalidArgumentException($this->l10n->t('%s-level scope is not supported for this policy', [$scopeLabel]));
	}

	public function saveUserPreference(string|\BackedEnum $policyKey, mixed $value): ResolvedPolicy {
		$context = $this->contextFactory->forCurrentUser();
		$definition = $this->registry->get($policyKey);
		$this->assertScopeSupported($definition, PolicySpec::SCOPE_USER);
		$normalizedValue = $definition->normalizeValue($value);
		$definition->validateValueForPersistence($normalizedValue, $context);
		$resolved = $this->resolver->resolve($definition, $context);
		if (!$resolved->canSaveAsUserDefault()) {
			// TRANSLATORS Error shown when saving a user preference for a policy that does not allow personal overrides. {policyKey} is the policy identifier.
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
		$definition->validateValueForPersistence($normalizedValue, $context);
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
	 * Save several values of the same composite policy family at the system layer.
	 *
	 * @param array<string, mixed> $values Values to persist, keyed by policy key
	 * @param array<string, bool> $allowChildOverride Override flag of each key, defaulting to false
	 * @return array<string, ResolvedPolicy>
	 */
	public function saveSystemCompound(string $parentPolicyKey, array $values, array $allowChildOverride = []): array {
		$this->validateCompositeWrite($parentPolicyKey, $values, true, $this->contextFactory->forUserId(null));

		$saved = [];
		$this->inTransaction(function () use ($values, $allowChildOverride, &$saved): void {
			foreach ($values as $policyKey => $value) {
				$saved[$policyKey] = $this->saveSystem($policyKey, $value, $allowChildOverride[$policyKey] ?? false);
			}
		});

		return $saved;
	}

	/**
	 * Save several values of the same composite policy family for one group.
	 *
	 * @param array<string, mixed> $values Values to persist, keyed by policy key
	 * @param array<string, bool> $allowChildOverride Override flag of each key, defaulting to false
	 * @return array<string, PolicyLayer>
	 */
	public function saveGroupPolicyCompound(string $parentPolicyKey, string $groupId, array $values, array $allowChildOverride = []): array {
		$this->validateCompositeWrite($parentPolicyKey, $values, false, $this->contextFactory->forUserId(null)->setGroups([$groupId]));

		$saved = [];
		$this->inTransaction(function () use ($groupId, $values, $allowChildOverride, &$saved): void {
			foreach ($values as $policyKey => $value) {
				$saved[$policyKey] = $this->saveGroupPolicy($policyKey, $groupId, $value, $allowChildOverride[$policyKey] ?? false);
			}
		});

		return $saved;
	}

	/**
	 * Save several values of the same composite policy family as the current
	 * user's personal defaults.
	 *
	 * @param array<string, mixed> $values Values to persist, keyed by policy key
	 * @return array<string, ResolvedPolicy>
	 */
	public function saveUserPreferenceCompound(string $parentPolicyKey, array $values): array {
		$this->validateCompositeWrite($parentPolicyKey, $values, false, $this->contextFactory->forCurrentUser());

		$saved = [];
		$this->inTransaction(function () use ($values, &$saved): void {
			foreach ($values as $policyKey => $value) {
				$saved[$policyKey] = $this->saveUserPreference($policyKey, $value);
			}
		});

		return $saved;
	}

	/**
	 * Save several values of the same composite policy family for one user.
	 *
	 * @param array<string, mixed> $values Values to persist, keyed by policy key
	 * @param array<string, bool> $allowChildOverride Override flag of each key, defaulting to false
	 * @return array<string, ?PolicyLayer>
	 */
	public function saveUserPolicyForUserIdCompound(string $parentPolicyKey, string $userId, array $values, array $allowChildOverride = []): array {
		$this->validateCompositeWrite($parentPolicyKey, $values, false, $this->contextFactory->forUserId($userId));

		$saved = [];
		$this->inTransaction(function () use ($userId, $values, $allowChildOverride, &$saved): void {
			foreach ($values as $policyKey => $value) {
				$saved[$policyKey] = $this->saveUserPolicyForUserId($policyKey, $userId, $value, $allowChildOverride[$policyKey] ?? false);
			}
		});

		return $saved;
	}

	/**
	 * Hand the complete intended configuration of a composite family to the
	 * policy that owns it, before a single key is written.
	 *
	 * The framework knows which keys belong together and which ones the caller
	 * is writing; the keys left out keep the value they already resolve to in
	 * the same scope, so the policy always sees the configuration the write is
	 * about to produce and the outcome does not depend on the order in which
	 * the keys are persisted. Whether that configuration makes sense is a
	 * question only the policy itself can answer.
	 *
	 * @param array<string, mixed> $values
	 */
	private function validateCompositeWrite(string $parentPolicyKey, array $values, bool $nullRestoresDefault, PolicyContext $context): void {
		$parentDefinition = $this->registry->get($parentPolicyKey);
		$definitions = $this->resolveCompositeFamily($parentDefinition, $values);
		$normalizedValues = $this->normalizeCompositeValues($definitions, $values, $nullRestoresDefault);

		$intendedValues = $normalizedValues;
		foreach ($definitions as $policyKey => $definition) {
			if (array_key_exists($policyKey, $intendedValues)) {
				continue;
			}

			$intendedValues[$policyKey] = $this->resolver->resolve($definition, $context)->getEffectiveValue();
		}

		$parentDefinition->validateCompositeValuesForPersistence(
			$intendedValues,
			array_keys($normalizedValues),
			$context,
		);
	}

	/**
	 * The definitions that make up a composite policy family, keyed by policy
	 * key, once every submitted key is known to belong to it.
	 *
	 * @param array<string, mixed> $values
	 * @return array<string, IPolicyDefinition>
	 */
	private function resolveCompositeFamily(IPolicyDefinition $parentDefinition, array $values): array {
		if ($parentDefinition->compositeChildren() === []) {
			// TRANSLATORS Error shown when several policy values are saved at once for a policy that has no other settings attached to it. {policyKey} is the policy identifier.
			throw new \InvalidArgumentException($this->l10n->t('{policyKey} does not group other policy settings', [
				'policyKey' => $parentDefinition->key(),
			]));
		}

		if ($values === []) {
			// TRANSLATORS Error shown when a request to save several policy values at once carries no value at all.
			throw new \InvalidArgumentException($this->l10n->t('No policy value was sent'));
		}

		$definitions = [$parentDefinition->key() => $parentDefinition];
		foreach ($parentDefinition->compositeChildren() as $childKey) {
			$definitions[$childKey] = $this->registry->get($childKey);
		}

		foreach (array_keys($values) as $policyKey) {
			if (isset($definitions[$policyKey])) {
				continue;
			}

			// TRANSLATORS Error shown when a policy value is saved together with settings it does not belong to. {policyKey} is the policy identifier and {parentPolicyKey} is the setting the others are grouped under.
			throw new \InvalidArgumentException($this->l10n->t('{policyKey} is not part of {parentPolicyKey}', [
				'policyKey' => (string)$policyKey,
				'parentPolicyKey' => $parentDefinition->key(),
			]));
		}

		return $definitions;
	}

	/**
	 * @param array<string, IPolicyDefinition> $definitions
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	private function normalizeCompositeValues(array $definitions, array $values, bool $nullRestoresDefault): array {
		$normalizedValues = [];
		foreach ($values as $policyKey => $value) {
			$definition = $definitions[$policyKey];
			$normalizedValues[$policyKey] = $nullRestoresDefault && $value === null
				? $definition->normalizeValue($definition->defaultSystemValue())
				: $definition->normalizeValue($value);
		}

		return $normalizedValues;
	}

	/**
	 * A composite write is one change made of several persisted values, so
	 * either all of them are stored or none is.
	 */
	private function inTransaction(callable $operation): void {
		$this->db->beginTransaction();
		try {
			$operation();
			$this->db->commit();
		} catch (\Throwable $exception) {
			$this->db->rollBack();

			throw $exception;
		}
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
