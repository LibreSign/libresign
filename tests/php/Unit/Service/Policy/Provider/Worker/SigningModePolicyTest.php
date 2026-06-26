<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Worker;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\Worker\SigningModePolicy;
use OCA\Libresign\Service\Policy\Provider\Worker\WorkerConfigPolicy;
use PHPUnit\Framework\TestCase;

final class SigningModePolicyTest extends TestCase {
	public function testProviderBuildsSigningModeDefinition(): void {
		$provider = new SigningModePolicy();
		$this->assertSame([
			SigningModePolicy::KEY_SIGNING_MODE,
		], $provider->keys());

		$signingMode = $provider->get(SigningModePolicy::KEY_SIGNING_MODE);
		$this->assertSame(SigningModePolicy::KEY_SIGNING_MODE, $signingMode->key());
		$this->assertSame('sync', $signingMode->defaultSystemValue());
		$this->assertSame(['sync', 'async'], $signingMode->allowedValues(new PolicyContext()));
		$this->assertSame('async', $signingMode->normalizeValue('async'));
		$this->assertSame('sync', $signingMode->normalizeValue('invalid-value'));
		$this->assertFalse($signingMode->supportsUserPreference());
		$this->assertSame(['system'], $signingMode->supportedScopes());
		$this->assertSame([WorkerConfigPolicy::KEY], $signingMode->compositeChildren());
	}

	public function testThrowsOnUnknownPolicyKey(): void {
		$provider = new SigningModePolicy();
		$this->expectException(\InvalidArgumentException::class);
		$provider->get('unknown_policy_key');
	}
}
