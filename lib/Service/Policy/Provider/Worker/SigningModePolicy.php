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
	public const KEY_WORKER_TYPE = 'worker_type';
	public const KEY_PARALLEL_WORKERS = 'parallel_workers';

	public const SYSTEM_APP_CONFIG_KEY_SIGNING_MODE = self::KEY_SIGNING_MODE;
	public const SYSTEM_APP_CONFIG_KEY_WORKER_TYPE = self::KEY_WORKER_TYPE;
	public const SYSTEM_APP_CONFIG_KEY_PARALLEL_WORKERS = self::KEY_PARALLEL_WORKERS;

	#[\Override]
	public function keys(): array {
		return [
			self::KEY_SIGNING_MODE,
			self::KEY_WORKER_TYPE,
			self::KEY_PARALLEL_WORKERS,
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
			),
			self::KEY_WORKER_TYPE => new PolicySpec(
				key: self::KEY_WORKER_TYPE,
				defaultSystemValue: 'local',
				allowedValues: ['local', 'external'],
				normalizer: static fn (mixed $rawValue): string => in_array((string)$rawValue, ['local', 'external'], true)
					? (string)$rawValue
					: 'local',
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY_WORKER_TYPE,
				supportsUserPreference: false,
			),
			self::KEY_PARALLEL_WORKERS => new PolicySpec(
				key: self::KEY_PARALLEL_WORKERS,
				defaultSystemValue: 4,
				allowedValues: static fn (): array => [],
				normalizer: static fn (mixed $rawValue): int => (int)$rawValue,
				validator: static function (mixed $value): void {
					if (!is_int($value) || $value < 1 || $value > 32) {
						throw new \InvalidArgumentException('parallel_workers must be between 1 and 32');
					}
				},
				appConfigKey: self::SYSTEM_APP_CONFIG_KEY_PARALLEL_WORKERS,
				supportsUserPreference: false,
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
