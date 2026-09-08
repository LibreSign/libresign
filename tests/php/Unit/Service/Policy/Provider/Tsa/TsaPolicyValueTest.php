<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Tsa;

use OCA\Libresign\Service\Policy\Provider\Tsa\TsaPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TsaPolicyValueTest extends TestCase {
	public function testDefaultsDescribeADisabledTsaUsingSha256(): void {
		$this->assertSame([
			'url' => '',
			'policy_oid' => '',
			'auth_type' => 'none',
			'username' => '',
			'hash_algorithm' => 'SHA256',
		], TsaPolicyValue::defaults());
	}

	#[DataProvider('providerHashAlgorithm')]
	public function testDecodeHashAlgorithm(mixed $rawValue, string $expected): void {
		$settings = TsaPolicyValue::decode(['url' => 'https://tsa.example.test/tsr', 'hash_algorithm' => $rawValue]);

		$this->assertSame($expected, $settings['hash_algorithm']);
	}

	public static function providerHashAlgorithm(): array {
		return [
			'SHA256 is kept' => ['SHA256', 'SHA256'],
			'SHA384 is kept' => ['SHA384', 'SHA384'],
			'SHA512 is kept' => ['SHA512', 'SHA512'],
			'lowercase is normalized' => ['sha384', 'SHA384'],
			'surrounding spaces are trimmed' => ['  SHA512  ', 'SHA512'],
			// A timestamp authority that still accepts SHA1 is the exception, and the
			// rejection it causes is what #8145 reports: it is not offered here.
			'SHA1 is not accepted' => ['SHA1', 'SHA256'],
			'RIPEMD160 is not accepted' => ['RIPEMD160', 'SHA256'],
			'an unknown algorithm falls back' => ['XYZ', 'SHA256'],
			'an empty algorithm falls back' => ['', 'SHA256'],
			'a non string falls back' => [256, 'SHA256'],
			'null falls back' => [null, 'SHA256'],
		];
	}

	public function testDecodeWithoutHashAlgorithmUsesTheDefault(): void {
		$settings = TsaPolicyValue::decode(['url' => 'https://tsa.example.test/tsr']);

		$this->assertSame('SHA256', $settings['hash_algorithm']);
	}

	public function testDecodeReadsTheHashAlgorithmFromJson(): void {
		$settings = TsaPolicyValue::decode('{"url":"https://tsa.example.test/tsr","hash_algorithm":"SHA512"}');

		$this->assertSame('SHA512', $settings['hash_algorithm']);
	}

	public function testEncodeKeepsTheHashAlgorithm(): void {
		$encoded = TsaPolicyValue::encode(['url' => 'https://tsa.example.test/tsr', 'hash_algorithm' => 'SHA384']);

		$this->assertSame('SHA384', TsaPolicyValue::decode($encoded)['hash_algorithm']);
	}
}
