<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\RequestSignGroups;

use OCA\Libresign\Service\Policy\Provider\RequestSignGroups\RequestSignGroupsPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestSignGroupsPolicyValueTest extends TestCase {
	/**
	 * @param list<string> $expected
	 */
	#[DataProvider('scopedGroupsProvider')]
	public function testDecodeScopedGroups(mixed $rawValue, array $expected): void {
		self::assertSame($expected, RequestSignGroupsPolicyValue::decodeScopedGroups($rawValue));
	}

	#[DataProvider('canUserGroupsRequestSignProvider')]
	public function testCanUserGroupsRequestSign(mixed $rawValue, array $userGroups, bool $expected): void {
		self::assertSame($expected, RequestSignGroupsPolicyValue::canUserGroupsRequestSign($rawValue, $userGroups));
	}

	/**
	 * @return iterable<string, array{0: mixed, 1: list<string>}>
	 */
	public static function scopedGroupsProvider(): iterable {
		yield 'json policy merges allow and deny groups' => [
			'{"allowGroups":[" finance ","board",""],"denyGroups":["legal","board"," ops "]}',
			['board', 'finance', 'legal', 'ops'],
		];

		yield 'legacy list format is still supported' => [
			['admin', ' board ', 'admin'],
			['admin', 'board'],
		];
	}

	/**
	 * @return iterable<string, array{0: mixed, 1: array<mixed>, 2: bool}>
	 */
	public static function canUserGroupsRequestSignProvider(): iterable {
		yield 'allows when user has at least one allowed group' => [
			'{"allowGroups":["admin","finance"],"denyGroups":[]}',
			['finance'],
			true,
		];

		yield 'denies when user has no allowed groups' => [
			'{"allowGroups":["admin","finance"],"denyGroups":[]}',
			['sales'],
			false,
		];

		yield 'denies when user matches denied group' => [
			'{"allowGroups":["board"],"denyGroups":["board"]}',
			['board'],
			false,
		];

		yield 'denies when normalized user groups are empty' => [
			'{"allowGroups":["admin"],"denyGroups":[]}',
			['', '   ', 123],
			false,
		];
	}
}
