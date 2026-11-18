<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignedCopyMail;

use OCA\Libresign\Service\Policy\Provider\SignedCopyMail\SignedCopyMailPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignedCopyMailPolicyValueTest extends TestCase {
	#[DataProvider('normalizeCases')]
	public function testNormalize(mixed $rawValue, array $expected): void {
		$this->assertSame($expected, SignedCopyMailPolicyValue::normalize($rawValue));
	}

	public static function normalizeCases(): array {
		return [
			'missing value is disabled' => [
				null,
				['enabled' => false, 'archive_addresses' => []],
			],
			'invalid json is disabled' => [
				'{not json',
				['enabled' => false, 'archive_addresses' => []],
			],
			'stored json is decoded' => [
				'{"enabled":true,"archive_addresses":["archive@example.com"]}',
				['enabled' => true, 'archive_addresses' => ['archive@example.com']],
			],
			'invalid and duplicate addresses are dropped' => [
				['enabled' => true, 'archive_addresses' => [' archive@example.com ', 'ARCHIVE@example.com', 'not-an-address', 42]],
				['enabled' => true, 'archive_addresses' => ['archive@example.com']],
			],
			'address text is split on commas and whitespace' => [
				['enabled' => 'yes', 'archive_addresses' => "one@example.com, two@example.com\nthree@example.com"],
				['enabled' => true, 'archive_addresses' => ['one@example.com', 'two@example.com', 'three@example.com']],
			],
			'unknown enabled value is disabled' => [
				['enabled' => 'maybe'],
				['enabled' => false, 'archive_addresses' => []],
			],
		];
	}

	public function testNormalizeLimitsArchiveAddresses(): void {
		$addresses = array_map(static fn (int $i): string => 'archive' . $i . '@example.com', range(1, 20));

		$normalized = SignedCopyMailPolicyValue::normalize(['enabled' => true, 'archive_addresses' => $addresses]);

		$this->assertCount(SignedCopyMailPolicyValue::MAX_ARCHIVE_ADDRESSES, $normalized['archive_addresses']);
	}
}
