<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy;

use OCA\Libresign\Service\Policy\PolicyContext;
use OCA\Libresign\Service\Policy\SignatureFlowPolicyDefinition;
use PHPUnit\Framework\TestCase;

final class SignatureFlowPolicyDefinitionTest extends TestCase {
	public function testKeyAndDefaultSystemValue(): void {
		$definition = new SignatureFlowPolicyDefinition();

		$this->assertSame('signature_flow', $definition->key());
		$this->assertSame('none', $definition->defaultSystemValue());
		$this->assertSame(['none', 'parallel', 'ordered_numeric'], $definition->allowedValues(new PolicyContext()));
	}

	public function testNormalizeValueConvertsNumericValues(): void {
		$definition = new SignatureFlowPolicyDefinition();

		$this->assertSame('none', $definition->normalizeValue(0));
		$this->assertSame('parallel', $definition->normalizeValue(1));
		$this->assertSame('ordered_numeric', $definition->normalizeValue(2));
		$this->assertSame('parallel', $definition->normalizeValue('parallel'));
	}

	public function testValidateValueRejectsUnexpectedValue(): void {
		$this->expectException(\InvalidArgumentException::class);

		$definition = new SignatureFlowPolicyDefinition();
		$definition->validateValue('invalid');
	}
}
