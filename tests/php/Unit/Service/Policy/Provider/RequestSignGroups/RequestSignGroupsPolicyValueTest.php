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
	 * @param array{allowGroups: list<string>, denyGroups: list<string>} $expected
	 */
	#[DataProvider('decodePolicyProvider')]
	public function testDecodePolicy(mixed $rawValue, array $expected): void {
		self::assertSame($expected, RequestSignGroupsPolicyValue::decodePolicy($rawValue));
	}

	/**
	 * @param list<string> $expected
	 */
	#[DataProvider('scopedGroupsProvider')]
	public function testDecodeScopedGroups(mixed $rawValue, array $expected): void {
		self::assertSame($expected, RequestSignGroupsPolicyValue::decodeScopedGroups($rawValue));
	}

	#[DataProvider('encodeProvider')]
	public function testEncode(mixed $rawValue, string $expected): void {
		self::assertSame($expected, RequestSignGroupsPolicyValue::encode($rawValue));
	}

	#[DataProvider('canUserGroupsRequestSignProvider')]
	public function testCanUserGroupsRequestSign(mixed $rawValue, array $userGroups, bool $expected): void {
		self::assertSame($expected, RequestSignGroupsPolicyValue::canUserGroupsRequestSign($rawValue, $userGroups));
	}

	/**
	 * @return iterable<string, array{0: mixed, 1: array{allowGroups: list<string>, denyGroups: list<string>}}>
	 */
	public static function decodePolicyProvider(): iterable {
		yield 'json canonical policy normalizes and sorts both lists' => [
			'{"allowGroups":[" finance ","admin","finance"],"denyGroups":[""," legal "]}',
			[
				'allowGroups' => ['admin', 'finance'],
				'denyGroups' => ['legal'],
			],
		];

		yield 'native canonical payload is normalized' => [
			[
				'allowGroups' => [' board ', 'admin', 'board'],
				'denyGroups' => ['legal', '', ' ops '],
			],
			[
				'allowGroups' => ['admin', 'board'],
				'denyGroups' => ['legal', 'ops'],
			],
		];

		yield 'json list payloads are ignored' => [
			'["admin"," board "]',
			[
				'allowGroups' => [],
				'denyGroups' => [],
			],
		];

		yield 'blank strings collapse to empty policy' => [
			'   ',
			[
				'allowGroups' => [],
				'denyGroups' => [],
			],
		];

		yield 'non array allow groups are ignored while valid deny groups survive' => [
			'{"allowGroups":"admin","denyGroups":[" legal "]}',
			[
				'allowGroups' => [],
				'denyGroups' => ['legal'],
			],
		];
	}

	/**
	 * @return iterable<string, array{0: mixed, 1: list<string>}>
	 */
	public static function scopedGroupsProvider(): iterable {
		yield 'json policy merges allow and deny groups' => [
			'{"allowGroups":[" finance ","board",""],"denyGroups":["legal","board"," ops "]}',
			['board', 'finance', 'legal', 'ops'],
		];

		yield 'native canonical payload merges allow and deny groups' => [
			[
				'allowGroups' => [' finance ', 'board', ''],
				'denyGroups' => ['legal', 'board', ' ops '],
			],
			['board', 'finance', 'legal', 'ops'],
		];

		yield 'native list arrays are ignored' => [
			['admin', ' board ', 'admin'],
			[],
		];

		yield 'json list payloads are ignored' => [
			'["admin", " board ", "admin"]',
			[],
		];

		yield 'invalid strings are ignored' => [
			'not-json',
			[],
		];
	}

	/**
	 * @return iterable<string, array{0: mixed, 1: string}>
	 */
	public static function encodeProvider(): iterable {
		yield 'native allow-list input becomes canonical policy object' => [
			[' finance ', 'admin', 'finance'],
			'{"allowGroups":["admin","finance"],"denyGroups":[]}',
		];

		yield 'canonical object input preserves deny groups' => [
			[
				'allowGroups' => ['board', ' admin '],
				'denyGroups' => [' legal ', 'board'],
			],
			'{"allowGroups":["admin","board"],"denyGroups":["board","legal"]}',
		];

		yield 'invalid payloads collapse to empty canonical object' => [
			'not-json',
			'{"allowGroups":[],"denyGroups":[]}',
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

		yield 'allows when user group is normalized and denied groups are unrelated' => [
			'{"allowGroups":[" finance ","admin"],"denyGroups":["legal"]}',
			[' finance ', 'finance', 123],
			true,
		];

		yield 'denies when canonical allow groups collapse to empty after normalization' => [
			'{"allowGroups":["", "   "],"denyGroups":[]}',
			['admin'],
			false,
		];

		yield 'denies json list payloads that are no longer canonical' => [
			'["admin"]',
			['admin'],
			false,
		];

		yield 'denies when normalized user groups are empty' => [
			'{"allowGroups":["admin"],"denyGroups":[]}',
			['', '   ', 123],
			false,
		];
	}
}
