<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Helper;

use OCA\Libresign\Service\Policy\Provider\Helper\PolicyKeyNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

enum TestPolicyKey: string {
	case SAMPLE = 'sample_policy';
}

final class PolicyKeyNormalizerTest extends TestCase {
	#[DataProvider('normalizeCases')]
	public function testNormalize(string|\BackedEnum $policyKey, string $expected): void {
		$this->assertSame($expected, PolicyKeyNormalizer::normalize($policyKey));
	}

	/**
	 * @return iterable<string, array{0: string|\BackedEnum, 1: string}>
	 */
	public static function normalizeCases(): iterable {
		yield 'keeps string values' => ['sample_policy', 'sample_policy'];
		yield 'converts backed enum values' => [TestPolicyKey::SAMPLE, 'sample_policy'];
	}
}
