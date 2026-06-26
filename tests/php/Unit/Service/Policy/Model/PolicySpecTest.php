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

	public function testResolvedStateMetaDefaultsToEmptyAndMayDependOnContext(): void {
		$defaultSpec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: [],
		);
		$contextAwareSpec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: [],
			resolvedStateMeta: static fn (PolicyContext $context): array => [
				'defaultSystemValue' => 'canonical-' . $context->getUserId(),
			],
		);

		$this->assertSame([], $defaultSpec->resolvedStateMeta(new PolicyContext()));
		$this->assertSame(
			['defaultSystemValue' => 'canonical-john'],
			$contextAwareSpec->resolvedStateMeta(PolicyContext::fromUserId('john')),
		);
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

	public function testAppConfigKeyMayBeConfiguredPerPolicy(): void {
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
			appConfigKey: 'configured.signature_flow',
		);

		$this->assertSame('configured.signature_flow', $spec->getAppConfigKey());
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

	public function testSupportedScopesDefaultToSystemGroupAndUser(): void {
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
		);

		$this->assertSame(['system', 'group', 'user'], $spec->supportedScopes());
		$this->assertTrue($spec->supportsScope('system'));
		$this->assertTrue($spec->supportsScope('group'));
		$this->assertTrue($spec->supportsScope('user'));
		$this->assertFalse($spec->supportsScope('request'));
	}

	public function testSupportedScopesMayBeRestrictedPerPolicy(): void {
		$spec = new PolicySpec(
			key: 'signing_mode',
			defaultSystemValue: 'sync',
			allowedValues: ['sync', 'async'],
			supportedScopes: [PolicySpec::SCOPE_SYSTEM],
		);

		$this->assertSame(['system'], $spec->supportedScopes());
		$this->assertTrue($spec->supportsScope('system'));
		$this->assertFalse($spec->supportsScope('group'));
		$this->assertFalse($spec->supportsScope('user'));
	}

	public function testStructuralMetadataDefaultsToPublicStandalonePolicy(): void {
		$spec = new PolicySpec(
			key: 'signature_flow',
			defaultSystemValue: 'none',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
		);

		$this->assertFalse($spec->isBackendOnly());
		$this->assertFalse($spec->isHelper());
		$this->assertNull($spec->parentPolicyKey());
		$this->assertSame([], $spec->compositeChildren());
	}

	public function testStructuralMetadataMayDescribeHelpersAndCompositePolicies(): void {
		$spec = new PolicySpec(
			key: 'worker_config',
			defaultSystemValue: '{}',
			allowedValues: [],
			helper: true,
			parentPolicyKey: 'signing_mode',
			compositeChildren: ['worker_type', 'parallel_workers'],
			backendOnly: true,
		);

		$this->assertTrue($spec->isBackendOnly());
		$this->assertTrue($spec->isHelper());
		$this->assertSame('signing_mode', $spec->parentPolicyKey());
		$this->assertSame(['worker_type', 'parallel_workers'], $spec->compositeChildren());
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
		];
	}

	/** @return array<string, array{0: PolicySpec, 1: string}> */
	public static function provideSupportFlagsDisabled(): array {
		return [
			'user preference' => [
				new PolicySpec(key: 'x', defaultSystemValue: 'none', allowedValues: [], supportsUserPreference: false),
				'supportsUserPreference',
			],
			'group admin delegation' => [
				new PolicySpec(key: 'x', defaultSystemValue: 'none', allowedValues: [], supportsGroupAdminDelegation: false),
				'supportsGroupAdminDelegation',
			],
		];
	}

	public function testSupportsGroupAdminDelegationDefaultsToFalse(): void {
		$spec = new PolicySpec(key: 'x', defaultSystemValue: 'none', allowedValues: []);

		$this->assertFalse($spec->supportsGroupAdminDelegation(), 'policies must opt-in to group-admin delegation');
	}

	public function testSupportsGroupAdminDelegationCanBeEnabled(): void {
		$spec = new PolicySpec(key: 'x', defaultSystemValue: 'none', allowedValues: [], supportsGroupAdminDelegation: true);

		$this->assertTrue($spec->supportsGroupAdminDelegation());
	}

	public function testValidateGroupAdminDelegatedValueIsNoOpByDefault(): void {
		$spec = new PolicySpec(key: 'x', defaultSystemValue: 'none', allowedValues: []);

		$spec->validateGroupAdminDelegatedValue('proposed', 'seed', new PolicyContext());

		$this->addToAssertionCount(1);
	}

	public function testDelegatedValueValidatorClosureIsInvokedDuringValidation(): void {
		$called = false;
		$spec = new PolicySpec(
			key: 'x',
			defaultSystemValue: 'none',
			allowedValues: [],
			supportsGroupAdminDelegation: true,
			delegatedValueValidator: static function (mixed $proposed, mixed $seed, PolicyContext $context) use (&$called): void {
				$called = true;
				self::assertSame('proposed', $proposed);
				self::assertSame('seed', $seed);
			},
		);

		$spec->validateGroupAdminDelegatedValue('proposed', 'seed', new PolicyContext());

		$this->assertTrue($called);
	}

	public function testDelegatedValueValidatorClosureExceptionIsPropagated(): void {
		$spec = new PolicySpec(
			key: 'x',
			defaultSystemValue: 'none',
			allowedValues: [],
			supportsGroupAdminDelegation: true,
			delegatedValueValidator: static function (): void {
				throw new \DomainException('delegation rule violated');
			},
		);

		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('delegation rule violated');
		$spec->validateGroupAdminDelegatedValue('proposed', 'seed', new PolicyContext());
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
