<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignatureBackground;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\SignatureBackground\SignatureBackgroundPolicy;
use PHPUnit\Framework\TestCase;

final class SignatureBackgroundPolicyTest extends TestCase {
	public function testProviderBuildsSignatureBackgroundDefinition(): void {
		$provider = new SignatureBackgroundPolicy();
		$this->assertSame([SignatureBackgroundPolicy::KEY], $provider->keys());
		$definition = $provider->get(SignatureBackgroundPolicy::KEY);

		$this->assertSame(SignatureBackgroundPolicy::KEY, $definition->key());
		$this->assertSame('default', $definition->defaultSystemValue());
		$this->assertSame(['default', 'custom', 'deleted'], $definition->allowedValues(new PolicyContext()));
	}

	public function testProviderNormalizesBackgroundTypeInputs(): void {
		$provider = new SignatureBackgroundPolicy();
		$definition = $provider->get(SignatureBackgroundPolicy::KEY);

		$this->assertSame('default', $definition->normalizeValue('default'));
		$this->assertSame('custom', $definition->normalizeValue('custom'));
		$this->assertSame('deleted', $definition->normalizeValue('deleted'));
		$this->assertSame('default', $definition->normalizeValue('unknown'));
		$this->assertSame('default', $definition->normalizeValue(1));
	}
}
