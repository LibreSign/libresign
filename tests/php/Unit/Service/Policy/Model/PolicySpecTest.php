<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Model;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PolicySpecTest extends TestCase {
	public function testKeyAndDefaultSystemValueReturnConfiguredValues(): void {
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: ['default' => 'none'],
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
		);

		$this->assertSame('signature_flow', $spec->key());
		$this->assertSame(['default' => 'none'], $spec->defaultSystemValue());
	}

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

	public function testStorageKeysMayBeConfiguredPerPolicy(): void {
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
			appConfigKey: 'configured.signature_flow',
			userPreferenceKey: 'user.signature_flow',
		);

		$this->assertSame('configured.signature_flow', $spec->getAppConfigKey());
		$this->assertSame('user.signature_flow', $spec->getUserPreferenceKey());
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

	#[DataProvider('provideRawValuesWithoutNormalizer')]
	public function testNormalizeValueReturnsRawValueWhenNoNormalizerIsConfigured(mixed $rawValue): void {
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
		);

		$this->assertSame($rawValue, $spec->normalizeValue($rawValue));
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

	#[DataProvider('provideUnconstrainedValues')]
	public function testValidationAllowsAnyValueWhenAllowedValuesIsEmpty(mixed $value): void {
		$spec = new PolicySpec(
			key: 'signature_text_template',
			defaultSystemValue: '',
			allowedValues: [],
		);

		$spec->validateValue($value, new PolicyContext());

		$this->addToAssertionCount(1);
	}

	#[DataProvider('provideConstrainedValidationCases')]
	public function testValidationAgainstDefinedAllowedValues(mixed $value, bool $shouldThrow): void {
		$spec = new PolicySpec(
			key: 'signature_render_mode',
			defaultSystemValue: 'default',
			allowedValues: ['default', 'graphic', 'text'],
		);

		if ($shouldThrow) {
			$this->expectException(\InvalidArgumentException::class);
		}

		$spec->validateValue($value, new PolicyContext());

		if (!$shouldThrow) {
			$this->assertTrue(true);
		}
	}

	public function testCustomValidatorIsUsedBeforeAllowedValuesValidation(): void {
		$validatorCalled = false;
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none'],
			validator: static function (mixed $value, PolicyContext $context) use (&$validatorCalled): void {
				$validatorCalled = true;
				self::assertSame('not-in-allowed-values', $value);
				self::assertSame('john', $context->getUserId());
			},
		);

		$spec->validateValue('not-in-allowed-values', PolicyContext::fromUserId('john'));

		$this->assertTrue($validatorCalled);
	}

	public function testCustomValidatorExceptionIsPropagated(): void {
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: [],
			validator: static function (mixed $value, PolicyContext $context): void {
				throw new \DomainException('custom validation failed');
			},
		);

		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('custom validation failed');
		$spec->validateValue('invalid', new PolicyContext());
	}

	#[DataProvider('provideSupportFlagDefaults')]
	public function testSupportFlagsDefaultToTrue(string $method): void {
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: [],
		);

		$this->assertTrue($spec->$method());
	}

	#[DataProvider('provideSupportFlagsDisabled')]
	public function testSupportFlagsCanBeDisabled(PolicySpec $spec, string $method): void {
		$this->assertFalse($spec->$method());
	}

	/** @return array<string, array{0: string}> */
	public static function provideSupportFlagDefaults(): array {
		return [
			'user preference' => ['supportsUserPreference'],
			'group admin configuration' => ['supportsGroupAdminConfiguration'],
		];
	}

	/** @return array<string, array{0: PolicySpec, 1: string}> */
	public static function provideSupportFlagsDisabled(): array {
		return [
			'user preference' => [
				new PolicySpec(key: 'x', defaultSystemValue: 'none', allowedValues: [], supportsUserPreference: false),
				'supportsUserPreference',
			],
			'group admin configuration' => [
				new PolicySpec(key: 'x', defaultSystemValue: 'none', allowedValues: [], supportsGroupAdminConfiguration: false),
				'supportsGroupAdminConfiguration',
			],
		];
	}

	/** @return array<string, array{0: mixed}> */
	public static function provideUnconstrainedValues(): array {
		return [
			'text value' => ['any free text'],
			'float value' => [12.5],
			'boolean value' => [true],
			'null value' => [null],
			'array value' => [['a' => 'b']],
			'object value' => [new \stdClass()],
		];
	}

	/** @return array<string, array{0: mixed}> */
	public static function provideRawValuesWithoutNormalizer(): array {
		return [
			'string value' => ['parallel'],
			'integer value' => [3],
			'array value' => [['key' => 'value']],
			'null value' => [null],
			'boolean false value' => [false],
			'float value' => [0.75],
			'object value' => [new \stdClass()],
		];
	}

	/** @return array<string, array{0: mixed, 1: bool}> */
	public static function provideConstrainedValidationCases(): array {
		return [
			'allowed default value' => ['default', false],
			'allowed graphic value' => ['graphic', false],
			'allowed text value' => ['text', false],
			'disallowed value by case sensitivity' => ['Graphic', true],
			'disallowed value by strict type' => [1, true],
			'disallowed value' => ['unsupported_mode', true],
		];
	}
}
