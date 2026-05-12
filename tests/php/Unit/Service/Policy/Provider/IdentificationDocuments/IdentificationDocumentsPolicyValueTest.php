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
	public function testNormalize(mixed $rawValue, bool $default, array $expected): void {
		$result = IdentificationDocumentsPolicyValue::normalize($rawValue, $default);
		$this->assertSame($expected, $result);
	}

	public static function provideNormalizeCases(): array {
		return [
			'bool true' => [true, false, ['enabled' => true, 'approvers' => ['admin']]],
			'bool false' => [false, true, ['enabled' => false, 'approvers' => ['admin']]],
			'int one' => [1, false, ['enabled' => true, 'approvers' => ['admin']]],
			'int zero' => [0, true, ['enabled' => false, 'approvers' => ['admin']]],
			'string true' => ['true', false, ['enabled' => true, 'approvers' => ['admin']]],
			'string one' => ['1', false, ['enabled' => true, 'approvers' => ['admin']]],
			'string false' => ['false', true, ['enabled' => false, 'approvers' => ['admin']]],
			'array with structure' => [
				['enabled' => true, 'approvers' => ['group1', 'group2']],
				false,
				['enabled' => true, 'approvers' => ['group1', 'group2']],
			],
			'array with empty approvers defaults' => [
				['enabled' => true, 'approvers' => []],
				false,
				['enabled' => true, 'approvers' => ['admin']],
			],
			'array filters empty strings' => [
				['enabled' => true, 'approvers' => ['group1', '', 'group2', '']],
				false,
				['enabled' => true, 'approvers' => ['group1', 'group2']],
			],
		];
	}

	#[DataProvider('provideIsEnabledCases')]
	public function testIsEnabled(mixed $rawValue, bool $default, bool $expected): void {
		$result = IdentificationDocumentsPolicyValue::isEnabled($rawValue, $default);
		$this->assertSame($expected, $result);
	}

	public static function provideIsEnabledCases(): array {
		return [
			'bool true' => [true, false, true],
			'bool false' => [false, false, false],
			'array enabled true' => [['enabled' => true, 'approvers' => ['admin']], false, true],
			'array enabled false' => [['enabled' => false, 'approvers' => ['admin']], false, false],
		];
	}

	#[DataProvider('provideGetApproversCases')]
	public function testGetApprovers(mixed $rawValue, array $expected): void {
		$result = IdentificationDocumentsPolicyValue::getApprovers($rawValue);
		$this->assertSame($expected, $result);
	}

	public static function provideGetApproversCases(): array {
		return [
			'bool defaults to admin' => [true, ['admin']],
			'array with custom approvers' => [
				['enabled' => true, 'approvers' => ['group1', 'group2']],
				['group1', 'group2'],
			],
			'array with empty approvers' => [
				['enabled' => true, 'approvers' => []],
				['admin'],
			],
		];
	}
}
