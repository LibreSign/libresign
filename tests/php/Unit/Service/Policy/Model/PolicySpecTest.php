<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Model;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use PHPUnit\Framework\TestCase;

final class PolicySpecTest extends TestCase {
	public function testDefaultStorageKeysFallbackToPolicyKey(): void {
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
		);

		$this->assertSame(PolicySpec::RESOLUTION_MODE_RESOLVED, $spec->resolutionMode());
		$this->assertSame('signature_flow', $spec->getAppConfigKey());
		$this->assertSame('policy.signature_flow', $spec->getUserPreferenceKey());
	}

	public function testResolutionModeMayBeConfiguredPerPolicy(): void {
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
			resolutionMode: PolicySpec::RESOLUTION_MODE_VALUE_CHOICE,
		);

		$this->assertSame(PolicySpec::RESOLUTION_MODE_VALUE_CHOICE, $spec->resolutionMode());
	}

	public function testNormalizerAndValidatorAreApplied(): void {
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
			normalizer: static fn (mixed $value): mixed => $value === 2 ? 'ordered_numeric' : $value,
		);

		$this->assertSame('ordered_numeric', $spec->normalizeValue(2));
		$spec->validateValue('parallel', new PolicyContext());
		$this->expectException(\InvalidArgumentException::class);
		$spec->validateValue('invalid', new PolicyContext());
	}

	public function testAllowedValuesMayDependOnContext(): void {
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: static fn (PolicyContext $context): array => $context->getUserId() === 'john'
				? ['parallel']
				: ['none'],
		);

		$this->assertSame(['parallel'], $spec->allowedValues(PolicyContext::fromUserId('john')));
		$this->assertSame(['none'], $spec->allowedValues(new PolicyContext()));
	}

	public function testValidationUsesProvidedContext(): void {
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: static fn (PolicyContext $context): array => $context->getUserId() === 'john' ? ['parallel'] : ['none'],
		);

		$spec->validateValue('parallel', PolicyContext::fromUserId('john'));

		$this->expectException(\InvalidArgumentException::class);
		$spec->validateValue('parallel', new PolicyContext());
	}
}
