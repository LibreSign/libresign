<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\ApprovalGroups;

use OCA\Libresign\Service\Policy\Provider\ApprovalGroups\ApprovalGroupsPolicyValue;
use PHPUnit\Framework\TestCase;

final class ApprovalGroupsPolicyValueTest extends TestCase {
	/** @dataProvider provideDecodeScenarios */
	public function testDecode(mixed $rawValue, array $expected): void {
		$this->assertSame($expected, ApprovalGroupsPolicyValue::decode($rawValue));
	}

	public static function provideDecodeScenarios(): array {
		return [
			'json string' => ['["finance","admin"]', ['admin', 'finance']],
			'csv string' => ['finance, admin', ['admin', 'finance']],
			'array value' => [['finance', 'admin'], ['admin', 'finance']],
			'invalid type' => [123, []],
			'empty string' => ['', []],
		];
	}

	public function testEncode(): void {
		$this->assertSame('["admin","finance"]', ApprovalGroupsPolicyValue::encode(['finance', 'admin']));
	}
}
