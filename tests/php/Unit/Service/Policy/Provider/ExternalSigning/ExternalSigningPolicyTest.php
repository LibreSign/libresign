<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\ExternalSigning;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\ExternalSigning\ExternalSigningPolicy;
use OCA\Libresign\Service\Policy\Provider\PolicyProviders;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExternalSigningPolicyTest extends TestCase {
	public function testRegisteredInPolicyRegistry(): void {
		self::assertSame(ExternalSigningPolicy::class, PolicyProviders::BY_KEY[ExternalSigningPolicy::KEY]);
	}

	public function testUsesDefaultDenyAcrossAllStandardScopes(): void {
		$definition = (new ExternalSigningPolicy())->get(ExternalSigningPolicy::KEY);
		self::assertFalse($definition->defaultSystemValue());
		self::assertSame(['system', 'group', 'user'], $definition->supportedScopes());
		self::assertFalse($definition->supportsRequestOverride());
		self::assertFalse($definition->supportsUserPreference());
		self::assertSame(PolicySpec::LIFECYCLE_RUNTIME, $definition->requestLifecycle());
		self::assertSame([false, true], $definition->allowedValues(new PolicyContext()));
	}

	#[DataProvider('provideValues')]
	public function testValidatesValuesStrictly(mixed $raw, mixed $normalized, bool $valid): void {
		$definition = (new ExternalSigningPolicy())->get(ExternalSigningPolicy::KEY);
		self::assertSame($normalized, $definition->normalizeValue($raw));
		if (!$valid) {
			$this->expectException(\InvalidArgumentException::class);
		}
		$definition->validateValue($normalized, new PolicyContext());
	}

	/** @return iterable<string, array{mixed, mixed, bool}> */
	public static function provideValues(): iterable {
		yield 'allowed' => [true, true, true];
		yield 'denied' => [false, false, true];
		yield 'true string' => ['true', true, true];
		yield 'false string' => ['false', false, true];
		yield 'invalid string' => ['yes', 'yes', false];
		yield 'invalid array' => [['enabled' => true], ['enabled' => true], false];
	}
}
