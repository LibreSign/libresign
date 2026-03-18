<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy;

use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\Provider\PolicyProviders;
use OCA\Libresign\Service\Policy\Runtime\DefaultPolicyResolver;
use OCA\Libresign\Service\Policy\Runtime\PolicyContextFactory;
use OCA\Libresign\Service\Policy\Runtime\PolicyRegistry;
use OCA\Libresign\Service\Policy\Runtime\PolicySource;
use OCP\IUser;

class PolicyService {
	private DefaultPolicyResolver $resolver;

	public function __construct(
		private PolicyContextFactory $contextFactory,
		private PolicySource $source,
		private PolicyRegistry $registry,
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

	public function saveSystem(string|\BackedEnum $policyKey, mixed $value): ResolvedPolicy {
		$context = $this->contextFactory->forCurrentUser();
		$definition = $this->registry->get($policyKey);
		$normalizedValue = $value === null
			? $definition->normalizeValue($definition->defaultSystemValue())
			: $definition->normalizeValue($value);

		$definition->validateValue($normalizedValue, $context);
		$this->source->saveSystemPolicy($definition->key(), $normalizedValue);

		return $this->resolver->resolve($definition, $context);
	}

	public function saveUserPreference(string|\BackedEnum $policyKey, mixed $value): ResolvedPolicy {
		$context = $this->contextFactory->forCurrentUser();
		$definition = $this->registry->get($policyKey);
		$resolved = $this->resolver->resolve($definition, $context);
		if (!$resolved->canSaveAsUserDefault()) {
			throw new \InvalidArgumentException('Saving a user preference is not allowed for ' . $definition->key());
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
}
