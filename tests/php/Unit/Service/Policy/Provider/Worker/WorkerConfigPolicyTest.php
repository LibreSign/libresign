<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Worker;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\Worker\WorkerConfigPolicy;
use PHPUnit\Framework\TestCase;

final class WorkerConfigPolicyTest extends TestCase {
	public function testProviderBuildsUnifiedWorkerConfigDefinition(): void {
		$provider = new WorkerConfigPolicy();
		$this->assertSame([
			WorkerConfigPolicy::KEY,
		], $provider->keys());

		$definition = $provider->get(WorkerConfigPolicy::KEY);
		$this->assertSame(WorkerConfigPolicy::KEY, $definition->key());
		$this->assertSame([], $definition->allowedValues(new PolicyContext()));

		$default = json_decode((string)$definition->defaultSystemValue(), true);
		$this->assertSame('local', $default['worker_type']);
		$this->assertSame(4, $default['parallel_workers']);
	}

	public function testNormalizeValueAcceptsValidValuesAndClampsParallelWorkers(): void {
		$provider = new WorkerConfigPolicy();

		$normalized = $provider->normalizeValue([
			'worker_type' => 'external',
			'parallel_workers' => 999,
		]);

		$this->assertSame('external', $normalized['worker_type']);
		$this->assertSame(32, $normalized['parallel_workers']);
	}

	public function testNormalizeValueFallsBackToDefaultsForInvalidPayload(): void {
		$provider = new WorkerConfigPolicy();

		$this->assertSame([
			'worker_type' => 'local',
			'parallel_workers' => 4,
		], $provider->normalizeValue('not-json'));

		$this->assertSame([
			'worker_type' => 'local',
			'parallel_workers' => 1,
		], $provider->normalizeValue(['worker_type' => 'invalid', 'parallel_workers' => 'abc']));
	}
}
