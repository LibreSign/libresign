<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Worker;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\Worker\SigningModePolicy;
use PHPUnit\Framework\TestCase;

final class SigningModePolicyTest extends TestCase {
	public function testProviderBuildsSigningModeDefinition(): void {
		$provider = new SigningModePolicy();
		$this->assertSame([
			SigningModePolicy::KEY_SIGNING_MODE,
			SigningModePolicy::KEY_WORKER_TYPE,
			SigningModePolicy::KEY_PARALLEL_WORKERS,
		], $provider->keys());

		$signingMode = $provider->get(SigningModePolicy::KEY_SIGNING_MODE);
		$this->assertSame(SigningModePolicy::KEY_SIGNING_MODE, $signingMode->key());
		$this->assertSame('sync', $signingMode->defaultSystemValue());
		$this->assertSame(['sync', 'async'], $signingMode->allowedValues(new PolicyContext()));
		$this->assertSame('async', $signingMode->normalizeValue('async'));
		$this->assertSame('sync', $signingMode->normalizeValue('invalid-value'));
		$this->assertFalse($signingMode->supportsUserPreference());

		$workerType = $provider->get(SigningModePolicy::KEY_WORKER_TYPE);
		$this->assertSame(SigningModePolicy::KEY_WORKER_TYPE, $workerType->key());
		$this->assertSame('local', $workerType->defaultSystemValue());
		$this->assertSame(['local', 'external'], $workerType->allowedValues(new PolicyContext()));
		$this->assertSame('external', $workerType->normalizeValue('external'));
		$this->assertSame('local', $workerType->normalizeValue('invalid-worker'));

		$parallelWorkers = $provider->get(SigningModePolicy::KEY_PARALLEL_WORKERS);
		$this->assertSame(SigningModePolicy::KEY_PARALLEL_WORKERS, $parallelWorkers->key());
		$this->assertSame(4, $parallelWorkers->defaultSystemValue());
		$this->assertSame(1, $parallelWorkers->normalizeValue(0));
		$this->assertSame(32, $parallelWorkers->normalizeValue(33));
		$this->assertSame(8, $parallelWorkers->normalizeValue(8));
	}

	public function testThrowsOnUnknownPolicyKey(): void {
		$provider = new SigningModePolicy();
		$this->expectException(\InvalidArgumentException::class);
		$provider->get('unknown_policy_key');
	}
}
