<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicy;
use OCA\Libresign\Service\Policy\Provider\PolicyProviders;
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
		$context = $this->contextFactory->forCurrentUser($requestOverrides, $activeContext);
		$definitions = [];
		foreach (array_keys(PolicyProviders::BY_KEY) as $policyKey) {
			$definitions[] = $this->registry->get($policyKey);
		}

		return $this->resolver->resolveMany($definitions, $context);
	}

	/** @return array<string, array<string, mixed>> */
	public function resolveKnownPolicyStates(array $requestOverrides = [], ?array $activeContext = null): array {
		return $this->serializeResolvedPolicies($this->resolveKnownPolicies($requestOverrides, $activeContext));
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
		return $this->source->loadUserPolicyConfig($definition->key(), $userId);
	}

	/**
	 * @return list<array{targetId: string, policy: PolicyLayer}>
	 */
	public function listUserPolicies(string|\BackedEnum $policyKey): array {
		$definition = $this->registry->get($policyKey);
		return $this->source->listUserPoliciesByKey($definition->key());
	}

	public function saveSystem(string|\BackedEnum $policyKey, mixed $value, bool $allowChildOverride = false): ResolvedPolicy {
		$context = $this->contextFactory->forCurrentUser();
		$definition = $this->registry->get($policyKey);
		$normalizedValue = $value === null
			? $definition->normalizeValue($definition->defaultSystemValue())
			: $definition->normalizeValue($value);

		$definition->validateValue($normalizedValue, $context);
		$this->source->saveSystemPolicy($definition->key(), $normalizedValue, $allowChildOverride);

		return $this->resolver->resolve($definition, $context);
	}

	public function clearSystem(string|\BackedEnum $policyKey): ResolvedPolicy {
		$context = $this->contextFactory->forCurrentUser();
		$definition = $this->registry->get($policyKey);
		$this->source->clearSystemPolicy($definition->key());

		return $this->resolver->resolve($definition, $context);
	}

	public function getGroupPolicy(string|\BackedEnum $policyKey, string $groupId): ?PolicyLayer {
		$definition = $this->registry->get($policyKey);
		return $this->source->loadGroupPolicyConfig($definition->key(), $groupId);
	}

	/**
	 * @return list<array{targetId: string, policy: PolicyLayer}>
	 */
	public function listGroupPolicies(string|\BackedEnum $policyKey): array {
		$definition = $this->registry->get($policyKey);
		return $this->source->listGroupPoliciesByKey($definition->key());
	}

	public function saveGroupPolicy(string|\BackedEnum $policyKey, string $groupId, mixed $value, bool $allowChildOverride): PolicyLayer {
		$definition = $this->registry->get($policyKey);
		$context = $this->contextFactory->forCurrentUser();
		$this->assertCurrentActorCanManageGroupPolicy($definition->key(), $context);
		$normalizedValue = $definition->normalizeValue($value);
		$definition->validateValue($normalizedValue, $context);
		$createdBySystemAdmin = ($context->getActorCapabilities()['canManageSystemPolicies'] ?? false) === true;
		$this->source->saveGroupPolicy(
			$definition->key(),
			$groupId,
			$normalizedValue,
			$allowChildOverride,
			$createdBySystemAdmin,
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
		$this->assertCurrentActorCanDeleteGroupPolicy($definition->key(), $groupId);
		$this->source->clearGroupPolicy($definition->key(), $groupId);

		return $this->source->loadGroupPolicyConfig($definition->key(), $groupId);
	}

	public function canDeleteGroupPolicy(string|\BackedEnum $policyKey, string $groupId, ?PolicyLayer $policy = null): bool {
		if ($this->contextFactory->isCurrentActorSystemAdmin()) {
			return true;
		}

		$definition = $this->registry->get($policyKey);
		$groupPolicy = $policy ?? $this->source->loadGroupPolicyConfig($definition->key(), $groupId);
		if (!$groupPolicy instanceof PolicyLayer) {
			return false;
		}

		$notes = $groupPolicy->getNotes();
		$createdBySystemAdmin = $notes['createdBySystemAdmin'] ?? null;
		if (is_bool($createdBySystemAdmin)) {
			return !$createdBySystemAdmin;
		}

		return ($notes['createdByActorScope'] ?? 'system') === 'group';
	}

	private function assertCurrentActorCanDeleteGroupPolicy(string $policyKey, string $groupId): void {
		if ($this->canDeleteGroupPolicy($policyKey, $groupId)) {
			return;
		}

		throw new \DomainException($this->l10n->t('Only system administrators can delete group rules created by a system administrator'));
	}

	private function assertCurrentActorCanManageGroupPolicy(string $policyKey, ?PolicyContext $context = null): void {
		$context ??= $this->contextFactory->forCurrentUser();
		if (($context->getActorCapabilities()['canManageSystemPolicies'] ?? false) === true) {
			return;
		}

		if ($policyKey === RequestSignGroupsPolicy::KEY) {
			if ($this->currentActorHasDelegatedRequestSignGroupsAccess($context)) {
				return;
			}

			throw new \DomainException($this->l10n->t('Group policy management requires explicit delegation from the system administrator'));
		}

		$systemPolicy = $this->source->loadSystemPolicy($policyKey);
		if ($systemPolicy === null || $systemPolicy->getScope() !== 'global' || !$systemPolicy->isAllowChildOverride()) {
			throw new \DomainException($this->l10n->t('Group policy management requires explicit delegation from the system administrator'));
		}
	}

	private function currentActorHasDelegatedRequestSignGroupsAccess(PolicyContext $context): bool {
		$actorCapabilities = $context->getActorCapabilities();
		if (($actorCapabilities['canManageGroupPolicies'] ?? false) !== true) {
			return false;
		}

		if ((int)($actorCapabilities['manageableGroupCount'] ?? 0) <= 1) {
			return false;
		}

		foreach ($this->source->loadGroupPolicies(RequestSignGroupsPolicy::KEY, $context) as $layer) {
			if (!$layer->isVisibleToChild()) {
				continue;
			}

			if (!$layer->isAllowChildOverride()) {
				continue;
			}

			if ($layer->getValue() === null) {
				continue;
			}

			return true;
		}

		return false;
	}

	public function saveUserPreference(string|\BackedEnum $policyKey, mixed $value): ResolvedPolicy {
		$context = $this->contextFactory->forCurrentUser();
		$definition = $this->registry->get($policyKey);
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
		$this->source->clearUserPreference($definition->key(), $context);

		return $this->resolver->resolve($definition, $context);
	}

	public function saveUserPolicyForUserId(string|\BackedEnum $policyKey, string $userId, mixed $value, bool $allowChildOverride): ?PolicyLayer {
		$context = $this->contextFactory->forUserId($userId);
		$definition = $this->registry->get($policyKey);
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
}
