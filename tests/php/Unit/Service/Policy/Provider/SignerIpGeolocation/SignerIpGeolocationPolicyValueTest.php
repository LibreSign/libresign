<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignerIpGeolocation;

use OCA\Libresign\Service\Policy\Provider\SignerIpGeolocation\SignerIpGeolocationPolicy;
use OCA\Libresign\Service\Policy\Provider\SignerIpGeolocation\SignerIpGeolocationPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignerIpGeolocationPolicyValueTest extends TestCase {
	#[DataProvider('provideNormalizeCases')]
	public function testNormalize(mixed $raw, array $expected): void {
		$this->assertSame($expected, SignerIpGeolocationPolicyValue::normalize($raw));
	}

	/** @return iterable<string, array{0: mixed, 1: array{mode: string}}> */
	public static function provideNormalizeCases(): iterable {
		yield 'null defaults disabled' => [null, ['mode' => 'disabled']];
		yield 'enabled' => [['mode' => 'enabled'], ['mode' => 'enabled']];
		yield 'invalid mode defaults disabled' => [['mode' => 'optional'], ['mode' => 'disabled']];
		yield 'json string' => ['{"mode":"enabled"}', ['mode' => 'enabled']];
	}

	public function testPolicyKey(): void {
		$this->assertSame('signer_ip_geolocation', SignerIpGeolocationPolicy::KEY);
	}
}
