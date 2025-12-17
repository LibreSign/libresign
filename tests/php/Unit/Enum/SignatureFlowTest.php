<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Enum;

use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SignatureFlowTest extends TestCase {
	public static function validFlowProvider(): array {
		return [
			'none' => [SignatureFlow::NONE, SignatureFlow::NUMERIC_NONE, 'none'],
			'parallel' => [SignatureFlow::PARALLEL, SignatureFlow::NUMERIC_PARALLEL, 'parallel'],
			'ordered_numeric' => [SignatureFlow::ORDERED_NUMERIC, SignatureFlow::NUMERIC_ORDERED_NUMERIC, 'ordered_numeric'],
		];
	}

	#[DataProvider('validFlowProvider')]
	public function testBidirectionalConversion(SignatureFlow $flow, int $expectedNumeric, string $expectedString): void {
		$this->assertEquals($expectedNumeric, $flow->toNumeric());
		$this->assertSame($flow, SignatureFlow::fromNumeric($expectedNumeric));
		$this->assertEquals($expectedString, $flow->value);
	}

	public static function invalidNumericProvider(): array {
		return [
			'negative' => [-1],
			'three' => [3],
			'large' => [999],
			'max_int' => [PHP_INT_MAX],
		];
	}

	#[DataProvider('invalidNumericProvider')]
	public function testFromNumericRejectsInvalidValues(int $invalidValue): void {
		$this->expectException(\ValueError::class);
		$this->expectExceptionMessage("Invalid numeric value for SignatureFlow: $invalidValue");
		SignatureFlow::fromNumeric($invalidValue);
	}
}
