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
		);
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
