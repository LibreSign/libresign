<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Runtime;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Provider\PolicyProviders;
use Psr\Container\ContainerInterface;

final class PolicyRegistry {
	/** @var array<string, IPolicyDefinition> */
	private array $definitions = [];

	public function __construct(
		private ContainerInterface $container,
	) {
	}

	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		$policyKeyValue = $this->normalizePolicyKey($policyKey);
		$definition = $this->definitions[$policyKeyValue] ?? null;
		if ($definition instanceof IPolicyDefinition) {
			return $definition;
		}

		$providerClass = PolicyProviders::BY_KEY[$policyKeyValue] ?? null;
		if (!is_string($providerClass) || $providerClass === '') {
			throw new \InvalidArgumentException('Unknown policy key: ' . $policyKeyValue);
		}

		$provider = $this->container->get($providerClass);
		if (!$provider instanceof IPolicyDefinitionProvider) {
			throw new \UnexpectedValueException('Invalid policy provider: ' . $providerClass);
		}

		$definition = $provider->get($policyKeyValue);
		if ($definition->key() !== $policyKeyValue) {
			throw new \InvalidArgumentException('Policy provider returned mismatched key: ' . $definition->key());
		}

		return $this->definitions[$policyKeyValue] = $definition;
	}

	private function normalizePolicyKey(string|\BackedEnum $policyKey): string {
		if ($policyKey instanceof \BackedEnum) {
			return (string)$policyKey->value;
		}

		return $policyKey;
	}
}
