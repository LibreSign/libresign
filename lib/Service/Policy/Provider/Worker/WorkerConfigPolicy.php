<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Worker;

use OCA\Libresign\Service\Policy\Contract\IPolicyDefinition;
use OCA\Libresign\Service\Policy\Contract\IPolicyDefinitionProvider;
use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;

final class WorkerConfigPolicy implements IPolicyDefinitionProvider {
	public const KEY = 'worker_config';
	public const SYSTEM_APP_CONFIG_KEY = 'worker_config';

	private const DEFAULT_WORKER_TYPE = 'local';
	private const DEFAULT_PARALLEL_WORKERS = 4;
	private const MIN_PARALLEL_WORKERS = 1;
	private const MAX_PARALLEL_WORKERS = 32;

	#[\Override]
	public function keys(): array {
		return [self::KEY];
	}

	#[\Override]
	public function get(string|\BackedEnum $policyKey): IPolicyDefinition {
		return new PolicySpec(
			key: self::KEY,
			defaultSystemValue: $this->encodeDefault(),
			allowedValues: [],
			normalizer: fn (mixed $rawValue): string => $this->encodeNormalized($rawValue),
			appConfigKey: self::SYSTEM_APP_CONFIG_KEY,
			supportsUserPreference: false,
			supportedScopes: [PolicySpec::SCOPE_SYSTEM],
			helper: true,
			parentPolicyKey: SigningModePolicy::KEY_SIGNING_MODE,
			compositeChildren: [SigningModePolicy::KEY_WORKER_TYPE, SigningModePolicy::KEY_PARALLEL_WORKERS],
			resolvedPolicyFinalizer: fn (ResolvedPolicy $resolved, PolicyContext $context, callable $resolvePolicy): ResolvedPolicy => $this->finalizeResolvedWorkerConfig($resolved, $resolvePolicy),
		);
	}

	private function finalizeResolvedWorkerConfig(ResolvedPolicy $resolved, callable $resolvePolicy): ResolvedPolicy {
		$effective = $this->normalizeValue($resolved->getEffectiveValue());
		$inherited = $this->normalizeValue($resolved->getInheritedValue());
		$defaults = $this->defaultValue();
		$currentScope = $resolved->getSourceScope();
		$currentPriority = $this->scopePriority($currentScope);

		foreach ([
			SigningModePolicy::KEY_WORKER_TYPE => 'worker_type',
			SigningModePolicy::KEY_PARALLEL_WORKERS => 'parallel_workers',
		] as $childKey => $field) {
			$childResolved = $resolvePolicy($childKey);
			$childScope = $childResolved->getSourceScope();
			$childPriority = $this->scopePriority($childScope);

			if ($childPriority < 0 || $childPriority < $currentPriority) {
				continue;
			}

			if ($childPriority === $currentPriority && ($effective[$field] ?? null) !== ($defaults[$field] ?? null)) {
				continue;
			}

			$effective[$field] = $field === 'worker_type'
				? ($childResolved->getEffectiveValue() === 'external' ? 'external' : 'local')
				: max(self::MIN_PARALLEL_WORKERS, min(self::MAX_PARALLEL_WORKERS, (int)$childResolved->getEffectiveValue()));
			$inherited[$field] = $field === 'worker_type'
				? (($childResolved->getInheritedValue() ?? $childResolved->getEffectiveValue()) === 'external' ? 'external' : 'local')
				: max(self::MIN_PARALLEL_WORKERS, min(self::MAX_PARALLEL_WORKERS, (int)($childResolved->getInheritedValue() ?? $childResolved->getEffectiveValue())));

			if ($childPriority >= $currentPriority) {
				$currentScope = $childScope;
				$currentPriority = $childPriority;
			}
		}

		return $resolved
			->setEffectiveValue(json_encode($effective, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))
			->setInheritedValue(json_encode($inherited, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))
			->setSourceScope($currentScope);
	}

	private function scopePriority(string $scope): int {
		return match ($scope) {
			'system' => -1,
			'global' => 0,
			'group' => 1,
			'user_policy' => 2,
			'user' => 3,
			'request' => 4,
			default => -1,
		};
	}

	/**
	 * @return array{worker_type: string, parallel_workers: int}
	 */
	public function defaultValue(): array {
		return [
			'worker_type' => self::DEFAULT_WORKER_TYPE,
			'parallel_workers' => self::DEFAULT_PARALLEL_WORKERS,
		];
	}

	/**
	 * @return array{worker_type: string, parallel_workers: int}
	 */
	public function normalizeValue(mixed $rawValue): array {
		if (is_string($rawValue)) {
			$decoded = json_decode($rawValue, true);
			if (is_array($decoded)) {
				$rawValue = $decoded;
			}
		}

		if (!is_array($rawValue)) {
			return $this->defaultValue();
		}

		$workerType = (string)($rawValue['worker_type'] ?? self::DEFAULT_WORKER_TYPE);
		if (!in_array($workerType, ['local', 'external'], true)) {
			$workerType = self::DEFAULT_WORKER_TYPE;
		}

		$parallelWorkers = (int)($rawValue['parallel_workers'] ?? self::DEFAULT_PARALLEL_WORKERS);
		$parallelWorkers = max(self::MIN_PARALLEL_WORKERS, min(self::MAX_PARALLEL_WORKERS, $parallelWorkers));

		return [
			'worker_type' => $workerType,
			'parallel_workers' => $parallelWorkers,
		];
	}

	private function encodeDefault(): string {
		return json_encode($this->defaultValue(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
	}

	private function encodeNormalized(mixed $rawValue): string {
		return json_encode($this->normalizeValue($rawValue), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
	}

}
