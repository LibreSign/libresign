<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\ObserverProfile;

use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ObserverProfilePolicyValueTest extends TestCase {
	#[DataProvider('provideValues')]
	public function testNormalize(mixed $value, bool $expected): void {
		$this->assertSame($expected, ObserverProfilePolicyValue::normalize($value));
	}

	public static function provideValues(): iterable {
		yield 'true boolean' => [true, true];
		yield 'true string' => ['true', true];
		yield 'one integer' => [1, true];
		yield 'false boolean' => [false, false];
		yield 'false string' => ['false', false];
		yield 'invalid value' => ['invalid', false];
	}
}
