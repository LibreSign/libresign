<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Db;

use OCA\Libresign\Db\IdentifyMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdentifyMethodTest extends TestCase {
	public static function uniqueIdentifierProvider(): array {
		return [
			'email identifier' => [
				'identifierKey' => 'email',
				'identifierValue' => 'user@example.com',
				'expected' => 'email:user@example.com',
			],
			'account identifier' => [
				'identifierKey' => 'account',
				'identifierValue' => 'john.doe',
				'expected' => 'account:john.doe',
			],
			'uuid identifier' => [
				'identifierKey' => 'uuid',
				'identifierValue' => '123e4567-e89b-12d3-a456-426614174000',
				'expected' => 'uuid:123e4567-e89b-12d3-a456-426614174000',
			],
			'phone identifier' => [
				'identifierKey' => 'phone',
				'identifierValue' => '+5511987654321',
				'expected' => 'phone:+5511987654321',
			],
			'value with colon' => [
				'identifierKey' => 'custom',
				'identifierValue' => 'domain:subdomain:value',
				'expected' => 'custom:domain:subdomain:value',
			],
			'empty value' => [
				'identifierKey' => 'email',
				'identifierValue' => '',
				'expected' => 'email:',
			],
			'numeric value' => [
				'identifierKey' => 'id',
				'identifierValue' => '12345',
				'expected' => 'id:12345',
			],
			'special characters' => [
				'identifierKey' => 'email',
				'identifierValue' => 'user+tag@sub.domain.com',
				'expected' => 'email:user+tag@sub.domain.com',
			],
		];
	}

	#[DataProvider('uniqueIdentifierProvider')]
	public function testGetUniqueIdentifier(
		string $identifierKey,
		string $identifierValue,
		string $expected,
	): void {
		$identifyMethod = new IdentifyMethod();
		$identifyMethod->setIdentifierKey($identifierKey);
		$identifyMethod->setIdentifierValue($identifierValue);

		$result = $identifyMethod->getUniqueIdentifier();

		$this->assertSame($expected, $result);
	}

	public function testUniqueIdentifierConsistency(): void {
		$identifyMethod = new IdentifyMethod();
		$identifyMethod->setIdentifierKey('email');
		$identifyMethod->setIdentifierValue('test@example.com');

		$first = $identifyMethod->getUniqueIdentifier();
		$second = $identifyMethod->getUniqueIdentifier();

		$this->assertSame($first, $second, 'Multiple calls should return consistent results');
	}

	public function testUniqueIdentifierAfterModification(): void {
		$identifyMethod = new IdentifyMethod();
		$identifyMethod->setIdentifierKey('email');
		$identifyMethod->setIdentifierValue('old@example.com');

		$before = $identifyMethod->getUniqueIdentifier();

		$identifyMethod->setIdentifierValue('new@example.com');
		$after = $identifyMethod->getUniqueIdentifier();

		$this->assertSame('email:old@example.com', $before);
		$this->assertSame('email:new@example.com', $after);
		$this->assertNotSame($before, $after, 'Identifier should change when value changes');
	}
}
