<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\DrawInkColor;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Model\PolicySpec;
use OCA\Libresign\Service\Policy\Provider\DrawInkColor\DrawInkColorPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DrawInkColorPolicyTest extends TestCase {
	public function testProviderBuildsBlackBySystemOnlyDefinition(): void {
		$provider = new DrawInkColorPolicy();
		$this->assertSame([DrawInkColorPolicy::KEY], $provider->keys());

		$definition = $provider->get(DrawInkColorPolicy::KEY);
		$this->assertSame(DrawInkColorPolicy::KEY, $definition->key());
		$this->assertSame('#000000', $definition->defaultSystemValue());
		$this->assertSame([PolicySpec::SCOPE_SYSTEM], $definition->supportedScopes());
		$this->assertFalse($definition->supportsUserPreference());
		$this->assertFalse($definition->supportsRequestOverride());
		$this->assertFalse($definition->supportsGroupAdminDelegation());
	}

	#[DataProvider('provideNormalizedValues')]
	public function testNormalizeValue(mixed $raw, string $expected): void {
		$definition = (new DrawInkColorPolicy())->get(DrawInkColorPolicy::KEY);
		$this->assertSame($expected, $definition->normalizeValue($raw));
	}

	public static function provideNormalizedValues(): array {
		return [
			['#1F2A5A', '#1f2a5a'],
			['  #1f2a5a ', '#1f2a5a'],
			['#000000', '#000000'],
		];
	}

	public function testValidateAcceptsHexColor(): void {
		$definition = (new DrawInkColorPolicy())->get(DrawInkColorPolicy::KEY);
		$definition->validateValue('#1f2a5a', new PolicyContext());
		$this->addToAssertionCount(1);
	}

	#[DataProvider('provideInvalidValues')]
	public function testValidateRejectsInvalidColor(mixed $value): void {
		$definition = (new DrawInkColorPolicy())->get(DrawInkColorPolicy::KEY);
		$this->expectException(\InvalidArgumentException::class);
		$definition->validateValue($definition->normalizeValue($value), new PolicyContext());
	}

	public static function provideInvalidValues(): array {
		return [
			[''],
			[null],
			['red'],
			['#fff'],
			['#12345g'],
			['1f2a5a'],
			['#1f2a5a; x'],
		];
	}

	public function testProviderRejectsUnknownKey(): void {
		$this->expectException(\InvalidArgumentException::class);
		(new DrawInkColorPolicy())->get('unknown');
	}
}
