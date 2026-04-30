<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\ValidationAccess;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\ValidationAccess\ValidationAccessPolicy;
use PHPUnit\Framework\TestCase;

final class ValidationAccessPolicyTest extends TestCase {
	public function testProviderBuildsValidationAccessDefinition(): void {
		$provider = new ValidationAccessPolicy();
		$this->assertSame([ValidationAccessPolicy::KEY], $provider->keys());
		$definition = $provider->get(ValidationAccessPolicy::KEY);

		$this->assertSame(ValidationAccessPolicy::KEY, $definition->key());
		$this->assertFalse($definition->defaultSystemValue());
		$this->assertSame([false, true], $definition->allowedValues(new PolicyContext()));
	}

	public function testProviderNormalizesValidationAccessBooleanInputs(): void {
		$provider = new ValidationAccessPolicy();
		$definition = $provider->get(ValidationAccessPolicy::KEY);

		$this->assertTrue($definition->normalizeValue('1'));
		$this->assertTrue($definition->normalizeValue('true'));
		$this->assertFalse($definition->normalizeValue('0'));
		$this->assertFalse($definition->normalizeValue('false'));
		$this->assertFalse($definition->normalizeValue('unexpected-value'));
	}
}
