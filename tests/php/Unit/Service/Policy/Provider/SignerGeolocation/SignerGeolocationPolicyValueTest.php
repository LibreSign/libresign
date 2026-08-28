<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignerGeolocation;

use OCA\Libresign\Enum\SignerGeolocationMode;
use OCA\Libresign\Service\Policy\Provider\SignerGeolocation\SignerGeolocationPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignerGeolocationPolicyValueTest extends TestCase {
	public function testDefaults(): void {
		$this->assertSame([
			'mode' => SignerGeolocationMode::DISABLED->value,
			'allowRequesterOverride' => false,
		], SignerGeolocationPolicyValue::defaults());
	}

	#[DataProvider('provideNormalizeCases')]
	public function testNormalize(mixed $input, array $expected): void {
		$this->assertSame($expected, SignerGeolocationPolicyValue::normalize($input));
	}

	/**
	 * @return iterable<string, array{0: mixed, 1: array{mode: string, allowRequesterOverride: bool}}>
	 */
	public static function provideNormalizeCases(): iterable {
		yield 'invalid input falls back to defaults' => [
			'invalid',
			SignerGeolocationPolicyValue::defaults(),
		];

		yield 'optional with requester override' => [
			['mode' => 'optional', 'allowRequesterOverride' => true],
			['mode' => 'optional', 'allowRequesterOverride' => true],
		];

		yield 'invalid mode falls back to disabled' => [
			['mode' => 'unknown', 'allowRequesterOverride' => false],
			['mode' => 'disabled', 'allowRequesterOverride' => false],
		];

		yield 'json string payload' => [
			'{"mode":"optional","allowRequesterOverride":true}',
			['mode' => 'optional', 'allowRequesterOverride' => true],
		];
	}

	public function testResolveEffectiveRequirementFromPolicy(): void {
		$this->assertSame(
			SignerGeolocationMode::REQUIRED,
			SignerGeolocationPolicyValue::getMode(['mode' => 'required', 'allowRequesterOverride' => false]),
		);
		$this->assertTrue(SignerGeolocationPolicyValue::isEnabled(['mode' => 'optional', 'allowRequesterOverride' => false]));
		$this->assertFalse(SignerGeolocationPolicyValue::isEnabled(['mode' => 'disabled', 'allowRequesterOverride' => false]));
	}
}
