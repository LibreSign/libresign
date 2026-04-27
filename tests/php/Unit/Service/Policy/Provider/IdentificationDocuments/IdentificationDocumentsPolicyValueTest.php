<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\IdentificationDocuments;

use OCA\Libresign\Service\Policy\Provider\IdentificationDocuments\IdentificationDocumentsPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentificationDocumentsPolicyValueTest extends TestCase {
	#[DataProvider('provideNormalizeCases')]
	public function testNormalize(mixed $rawValue, bool $default, bool $expected): void {
		$this->assertSame($expected, IdentificationDocumentsPolicyValue::normalize($rawValue, $default));
	}

	public static function provideNormalizeCases(): array {
		return [
			'bool true' => [true, false, true],
			'bool false' => [false, true, false],
			'int one' => [1, false, true],
			'int zero' => [0, true, false],
			'int unknown uses default false' => [2, false, false],
			'int unknown uses default true' => [2, true, true],
			'string true' => ['true', false, true],
			'string one' => ['1', false, true],
			'string false' => ['false', true, false],
			'string zero' => ['0', true, false],
			'empty string false' => ['', true, false],
			'whitespace false' => ['   ', true, false],
			'unexpected string uses default false' => ['enabled', false, false],
			'unexpected string uses default true' => ['enabled', true, true],
			'array uses default false' => [[1], false, false],
			'array uses default true' => [[1], true, true],
			'null uses default false' => [null, false, false],
			'null uses default true' => [null, true, true],
		];
	}
}
