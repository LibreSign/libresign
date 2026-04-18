<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
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

	public function getSystemPolicy(string|\BackedEnum $policyKey): ?PolicyLayer {
		$definition = $this->registry->get($policyKey);
		return $this->source->loadSystemPolicy($definition->key());
	}

	public function getUserPolicyForUserId(string|\BackedEnum $policyKey, string $userId): ?PolicyLayer {
		$definition = $this->registry->get($policyKey);
		return $this->source->loadUserPolicyConfig($definition->key(), $userId);
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

	public function getGroupPolicy(string|\BackedEnum $policyKey, string $groupId): ?PolicyLayer {
		$definition = $this->registry->get($policyKey);
		return $this->source->loadGroupPolicyConfig($definition->key(), $groupId);
	}

	public function saveGroupPolicy(string|\BackedEnum $policyKey, string $groupId, mixed $value, bool $allowChildOverride): PolicyLayer {
		$definition = $this->registry->get($policyKey);
		$this->assertCurrentActorCanManageGroupOverride($definition->key());
		$context = $this->contextFactory->forCurrentUser();
		$normalizedValue = $definition->normalizeValue($value);
		$definition->validateValue($normalizedValue, $context);
		$this->source->saveGroupPolicy($definition->key(), $groupId, $normalizedValue, $allowChildOverride);

		return $this->source->loadGroupPolicyConfig($definition->key(), $groupId)
			?? (new PolicyLayer())
				->setScope('group')
				->setVisibleToChild(true)
				->setAllowChildOverride(true)
				->setAllowedValues([]);
	}

	public function clearGroupPolicy(string|\BackedEnum $policyKey, string $groupId): ?PolicyLayer {
		$definition = $this->registry->get($policyKey);
		$this->assertCurrentActorCanManageGroupOverride($definition->key());
		$this->source->clearGroupPolicy($definition->key(), $groupId);

		return $this->source->loadGroupPolicyConfig($definition->key(), $groupId);
	}

	private function assertCurrentActorCanManageGroupOverride(string $policyKey): void {
		if ($this->contextFactory->isCurrentActorSystemAdmin()) {
			return;
		}

		$systemPolicy = $this->source->loadSystemPolicy($policyKey);
		if ($systemPolicy !== null && !$systemPolicy->isAllowChildOverride()) {
			throw new \DomainException($this->l10n->t('Lower-level overrides are not allowed for this policy'));
		}
	}

	public function saveUserPreference(string|\BackedEnum $policyKey, mixed $value): ResolvedPolicy {
		$context = $this->contextFactory->forCurrentUser();
		$definition = $this->registry->get($policyKey);
		$resolved = $this->resolver->resolve($definition, $context);
		if (!$resolved->canSaveAsUserDefault()) {
			throw new \InvalidArgumentException($this->l10n->t('Saving a user preference is not allowed for {policyKey}', [
				'policyKey' => $definition->key(),
			]));
		}

		$normalizedValue = $definition->normalizeValue($value);
		$definition->validateValue($normalizedValue, $context);
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
	 * @return array<string, array{groupCount: int, userCount: int}>
	 */
	public function getRuleCounts(array $groupIds, array $userIds): array {
		return $this->source->loadRuleCounts($groupIds, $userIds);
	}

	/** @return array<string, array{groupCount: int, userCount: int}> */
	public function getAllRuleCounts(): array {
		return $this->source->loadAllRuleCounts();
	}
}
