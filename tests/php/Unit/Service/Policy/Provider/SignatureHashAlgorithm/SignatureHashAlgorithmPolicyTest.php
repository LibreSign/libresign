<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignatureHashAlgorithm;

use OCA\Libresign\Service\Policy\Provider\SignatureHashAlgorithm\SignatureHashAlgorithmPolicy;
use PHPUnit\Framework\TestCase;

final class SignatureHashAlgorithmPolicyTest extends TestCase {
	public function testProviderBuildsDefinition(): void {
		$provider = new SignatureHashAlgorithmPolicy();
		$this->assertSame([SignatureHashAlgorithmPolicy::KEY], $provider->keys());

		$definition = $provider->get(SignatureHashAlgorithmPolicy::KEY);
		$this->assertSame(SignatureHashAlgorithmPolicy::KEY, $definition->key());
		$this->assertSame('SHA256', $definition->defaultSystemValue());
	}

	public function testNormalizesToAllowedAlgorithm(): void {
		$provider = new SignatureHashAlgorithmPolicy();
		$definition = $provider->get(SignatureHashAlgorithmPolicy::KEY);

		$this->assertSame('SHA512', $definition->normalizeValue('sha512'));
		$this->assertSame('SHA256', $definition->normalizeValue('unknown'));
	}
}
