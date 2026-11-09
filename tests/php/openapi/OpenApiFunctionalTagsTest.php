<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\OpenApi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OpenApiFunctionalTagsTest extends TestCase {
	private static function root(): string {
		return dirname(__DIR__, 3);
	}

	/**
	 * @return array{operationId: string, method: string, path: string, tags: list<string>, raw: array<string, mixed>}
	 */
	private static function operation(
		array $tags,
		string $operationId = 'example-operation',
		string $method = 'GET',
		string $path = '/example',
	): array {
		return [
			'operationId' => $operationId,
			'method' => $method,
			'path' => $path,
			'tags' => $tags,
			'raw' => [
				'operationId' => $operationId,
				'tags' => $tags,
			],
		];
	}

	public static function specificationProvider(): array {
		return [
			'default scope' => ['openapi.json'],
			'administration scope' => ['openapi-administration.json'],
			'full scope' => ['openapi-full.json'],
		];
	}

	#[DataProvider('specificationProvider')]
	public function testSpecificationUsesOneApprovedTag(string $file): void {
		$operations = OpenApiFunctionalTags::loadOperations(self::root() . '/' . $file);

		$this->assertSame([], OpenApiFunctionalTags::collectErrors($file, $operations));
	}

	public function testScopedSpecificationsDoNotOverlap(): void {
		$default = OpenApiFunctionalTags::indexOperations(
			'openapi.json',
			OpenApiFunctionalTags::loadOperations(self::root() . '/openapi.json'),
		);
		$administration = OpenApiFunctionalTags::indexOperations(
			'openapi-administration.json',
			OpenApiFunctionalTags::loadOperations(self::root() . '/openapi-administration.json'),
		);

		$this->assertSame([], $default['errors']);
		$this->assertSame([], $administration['errors']);
		$this->assertSame([], OpenApiFunctionalTags::collectScopeOverlapErrors($default, $administration));
	}

	public function testFullSpecificationMatchesScopedUnion(): void {
		$default = OpenApiFunctionalTags::indexOperations(
			'openapi.json',
			OpenApiFunctionalTags::loadOperations(self::root() . '/openapi.json'),
		);
		$administration = OpenApiFunctionalTags::indexOperations(
			'openapi-administration.json',
			OpenApiFunctionalTags::loadOperations(self::root() . '/openapi-administration.json'),
		);
		$full = OpenApiFunctionalTags::indexOperations(
			'openapi-full.json',
			OpenApiFunctionalTags::loadOperations(self::root() . '/openapi-full.json'),
		);

		$unionById = $default['byId'] + $administration['byId'];
		$unionByRoute = $default['byRoute'] + $administration['byRoute'];
		$this->assertSame(
			[],
			OpenApiFunctionalTags::collectFullSpecConsistencyErrors(
				array_keys($unionById),
				array_keys($full['byId']),
				array_keys($unionByRoute),
				array_keys($full['byRoute']),
			),
		);

		foreach ($unionById as $id => $operation) {
			$this->assertSame(
				$operation['raw'],
				$full['byId'][$id]['raw'],
				"Scoped operation {$id} raw OpenAPI object must match openapi-full.json",
			);
		}
	}

	public function testApprovedTagProducesNoError(): void {
		$this->assertSame(
			[],
			OpenApiFunctionalTags::collectErrors('fixture', [self::operation(['signing'])]),
		);
	}

	public static function invalidOperationProvider(): array {
		return [
			'former tag notify' => [['notify'], "unapproved tag 'notify'"],
			'missing tag' => [[], 'exactly one functional tag'],
			'multiple tags' => [['signing', 'requests'], 'exactly one functional tag'],
			'unapproved tag' => [['billing'], "unapproved tag 'billing'"],
		];
	}

	#[DataProvider('invalidOperationProvider')]
	public function testInvalidOperationIsReported(array $tags, string $needle): void {
		$errors = OpenApiFunctionalTags::collectErrors('fixture', [self::operation($tags)]);

		$this->assertNotSame([], $errors);
		$this->assertStringContainsString($needle, implode("\n", $errors));
	}

	public function testDuplicateOperationIdIsReportedWithoutOverwriting(): void {
		$first = self::operation(['signing'], 'example-operation', 'GET', '/one');
		$second = self::operation(['requests'], 'example-operation', 'POST', '/two');
		$indexed = OpenApiFunctionalTags::indexOperations('fixture', [$first, $second]);

		$this->assertNotSame([], $indexed['errors']);
		$this->assertStringContainsString('Duplicate operationId example-operation', implode("\n", $indexed['errors']));
		$this->assertSame($first, $indexed['byId']['example-operation']);
	}

	public function testDuplicateMethodAndPathIsReported(): void {
		$errors = OpenApiFunctionalTags::collectErrors('fixture', [
			self::operation(['signing'], 'first-operation', 'POST', '/same'),
			self::operation(['requests'], 'second-operation', 'POST', '/same'),
		]);

		$this->assertNotSame([], $errors);
		$this->assertStringContainsString('Duplicate POST /same', implode("\n", $errors));
	}

	public function testScopedSpecificationsSharingOperationIdAreReported(): void {
		$default = OpenApiFunctionalTags::indexOperations('openapi.json', [
			self::operation(['signing'], 'shared-id', 'GET', '/default'),
		]);
		$administration = OpenApiFunctionalTags::indexOperations('openapi-administration.json', [
			self::operation(['system'], 'shared-id', 'GET', '/admin'),
		]);

		$errors = OpenApiFunctionalTags::collectScopeOverlapErrors($default, $administration);
		$this->assertNotSame([], $errors);
		$this->assertStringContainsString('share operationId shared-id', implode("\n", $errors));
	}

	public function testScopedSpecificationsSharingMethodAndPathAreReported(): void {
		$default = OpenApiFunctionalTags::indexOperations('openapi.json', [
			self::operation(['signing'], 'default-id', 'GET', '/same'),
		]);
		$administration = OpenApiFunctionalTags::indexOperations('openapi-administration.json', [
			self::operation(['system'], 'admin-id', 'GET', '/same'),
		]);

		$errors = OpenApiFunctionalTags::collectScopeOverlapErrors($default, $administration);
		$this->assertNotSame([], $errors);
		$this->assertStringContainsString('share GET /same', implode("\n", $errors));
	}
}
