<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\CrlValidation;

use OCA\Libresign\Service\Policy\Provider\CrlValidation\CrlValidationPolicy;
use PHPUnit\Framework\TestCase;

final class CrlValidationPolicyTest extends TestCase {
	public function testProviderBuildsCrlValidationDefinition(): void {
		$provider = new CrlValidationPolicy();
		$this->assertSame([CrlValidationPolicy::KEY], $provider->keys());

		$definition = $provider->get(CrlValidationPolicy::KEY);
		$this->assertSame(CrlValidationPolicy::KEY, $definition->key());
		$this->assertTrue($definition->normalizeValue('true'));
		$this->assertFalse($definition->normalizeValue('false'));
		$this->assertFalse($definition->normalizeValue(null));
	}
}
