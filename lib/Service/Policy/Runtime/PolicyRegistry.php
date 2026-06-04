<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Runtime;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use Psr\Container\ContainerInterface;

final class PolicyRegistry {
	/** @var array<string, class-string<IPolicyDefinitionProvider>> */
	private array $keyToProviderClass = [];
	/** @var array<string, IPolicyDefinitionProvider> */
	private array $providerInstances = [];
	/** @var array<string, IPolicyDefinition> */
	private array $definitions = [];

	/**
	 * @param list<class-string<IPolicyDefinitionProvider>> $providerClasses
	 */
	public function __construct(
		private ContainerInterface $container,
		array $providerClasses = [],
	) {
		foreach ($providerClasses as $providerClass) {
			$provider = $this->container->get($providerClass);
			if (!$provider instanceof IPolicyDefinitionProvider) {
				throw new \UnexpectedValueException('Invalid policy provider: ' . $providerClass);
			}
			$this->providerInstances[$providerClass] = $provider;
			foreach ($provider->keys() as $key) {
				$this->keyToProviderClass[$key] = $providerClass;
			}
		}
	}

	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		$policyKeyValue = $this->normalizePolicyKey($policyKey);
		$definition = $this->definitions[$policyKeyValue] ?? null;
		if ($definition instanceof IPolicyDefinition) {
			return $definition;
		}

		$providerClass = $this->keyToProviderClass[$policyKeyValue] ?? null;
		if (!is_string($providerClass) || $providerClass === '') {
			throw new \InvalidArgumentException('Unknown policy key: ' . $policyKeyValue);
		}

		$provider = $this->providerInstances[$providerClass] ?? null;
		if (!$provider instanceof IPolicyDefinitionProvider) {
			throw new \UnexpectedValueException('Invalid policy provider: ' . $providerClass);
		}

		$definition = $provider->get($policyKeyValue);
		if ($definition->key() !== $policyKeyValue) {
			throw new \InvalidArgumentException('Policy provider returned mismatched key: ' . $definition->key());
		}

		return $this->definitions[$policyKeyValue] = $definition;
	}

	/** @return list<string> */
	public function getAllPolicyKeys(): array {
		return array_keys($this->keyToProviderClass);
	}

	private function normalizePolicyKey(string|\BackedEnum $policyKey): string {
		if ($policyKey instanceof \BackedEnum) {
			return (string)$policyKey->value;
		}

		return $policyKey;
	}
}
