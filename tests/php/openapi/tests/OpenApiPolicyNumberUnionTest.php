<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\OpenApi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpenApiPolicyNumberUnionTest extends TestCase {
	public static function specificationProvider(): array {
		return [
			'default scope' => ['openapi.json'],
			'administration scope' => ['openapi-administration.json'],
			'full scope' => ['openapi-full.json'],
		];
	}

	#[DataProvider('specificationProvider')]
	public function testPolicyValueUsesDisjointJsonNumberBranch(string $file): void {
		$source = file_get_contents(dirname(__DIR__, 4) . '/' . $file);
		self::assertNotFalse($source);

		$spec = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
		$schema = $spec['components']['schemas']['EffectivePolicyValue'] ?? null;
		self::assertIsArray($schema);
		self::assertArrayNotHasKey('anyOf', $schema);
		self::assertArrayHasKey('oneOf', $schema);

		// JSON numbers include both integers and decimals. A separate integer
		// branch would overlap with number and change oneOf semantics.
		$types = array_map(
			static fn (array $item): ?string => $item['type'] ?? null,
			$schema['oneOf'],
		);
		sort($types);
		self::assertSame(['boolean', 'number', 'object', 'string'], $types);
	}
}
