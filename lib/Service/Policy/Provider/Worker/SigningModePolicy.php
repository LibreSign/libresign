<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Worker;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicySpec;

final class SigningModePolicy implements IPolicyDefinitionProvider {
	public const KEY_SIGNING_MODE = 'signing_mode';

	public const SYSTEM_APP_CONFIG_KEY_SIGNING_MODE = self::KEY_SIGNING_MODE;

	#[\Override]
	public function keys(): array {
		return [
			self::KEY_SIGNING_MODE,
		];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		return match ($this->normalizePolicyKey($policyKey)) {
			self::KEY_SIGNING_MODE => new PolicySpec(
				key: self::KEY_SIGNING_MODE,
				defaultSystemValue: 'sync',
				allowedValues: ['sync', 'async'],
				normalizer: static fn (mixed $rawValue): string => in_array((string)$rawValue, ['sync', 'async'], true)
					? (string)$rawValue
					: 'sync',
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY_SIGNING_MODE,
				supportsUserPreference: false,
				supportedScopes: [PolicySpec::SCOPE_SYSTEM],
				compositeChildren: [WorkerConfigPolicy::KEY],
			),
			default => throw new \InvalidArgumentException('Unknown policy key: ' . $this->normalizePolicyKey($policyKey)),
		};
	}

	private function normalizePolicyKey(string|\BackedEnum $policyKey): string {
		if ($policyKey instanceof \BackedEnum) {
			return (string)$policyKey->value;
		}

		return $policyKey;
	}
}
