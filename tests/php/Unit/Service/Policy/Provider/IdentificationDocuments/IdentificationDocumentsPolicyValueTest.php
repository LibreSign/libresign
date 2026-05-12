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
			'structured payload enabled' => [
				['enabled' => true, 'approvers' => ['admin']],
				false,
				['enabled' => true, 'approvers' => ['admin']],
			],
			'structured payload with custom approvers' => [
				['enabled' => true, 'approvers' => ['group1', 'group2']],
				false,
				['enabled' => true, 'approvers' => ['group1', 'group2']],
			],
			'structured payload disabled' => [
				['enabled' => false, 'approvers' => ['admin']],
				false,
				['enabled' => false, 'approvers' => ['admin']],
			],
			'structured payload with empty approvers defaults' => [
				['enabled' => true, 'approvers' => []],
				false,
				['enabled' => true, 'approvers' => ['admin']],
			],
			'structured payload filters empty strings' => [
				['enabled' => true, 'approvers' => ['group1', '', 'group2', '']],
				false,
				['enabled' => true, 'approvers' => ['group1', 'group2']],
			],
			'non-array falls back to default' => [
				'invalid',
				false,
				['enabled' => false, 'approvers' => ['admin']],
			],
			'null falls back to default' => [
				null,
				true,
				['enabled' => true, 'approvers' => ['admin']],
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
			'array enabled true' => [
				['enabled' => true, 'approvers' => ['admin']],
				false,
				true,
			],
			'array enabled false' => [
				['enabled' => false, 'approvers' => ['admin']],
				false,
				false,
			],
			'invalid type uses default' => [
				'invalid',
				true,
				true,
			],
		];
	}

	#[DataProvider('provideGetApproversCases')]
	public function testGetApprovers(mixed $rawValue, array $expected): void {
		$result = IdentificationDocumentsPolicyValue::getApprovers($rawValue);
		$this->assertSame($expected, $result);
	}

	public static function provideGetApproversCases(): array {
		return [
			'array with custom approvers' => [
				['enabled' => true, 'approvers' => ['group1', 'group2']],
				['group1', 'group2'],
			],
			'array with empty approvers defaults' => [
				['enabled' => true, 'approvers' => []],
				['admin'],
			],
			'invalid type defaults to admin' => [
				'invalid',
				['admin'],
			],
		];
	}
}
