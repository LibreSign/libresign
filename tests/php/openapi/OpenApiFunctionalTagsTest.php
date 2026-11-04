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
	 * @return array{operationId: string, method: string, path: string, tags: list<string>}
	 */
	private static function operation(array $tags, string $operationId = 'example-operation'): array {
		return [
			'operationId' => $operationId,
			'method' => 'GET',
			'path' => '/example',
			'tags' => $tags,
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

	public function testScopedSpecificationsDoNotShareOperationIds(): void {
		$default = OpenApiFunctionalTags::indexById(OpenApiFunctionalTags::loadOperations(self::root() . '/openapi.json'));
		$administration = OpenApiFunctionalTags::indexById(OpenApiFunctionalTags::loadOperations(self::root() . '/openapi-administration.json'));

		$this->assertSame([], array_keys(array_intersect_key($default, $administration)));
	}

	public function testUnionMatchesGoldenOperationMap(): void {
		$default = OpenApiFunctionalTags::indexById(OpenApiFunctionalTags::loadOperations(self::root() . '/openapi.json'));
		$administration = OpenApiFunctionalTags::indexById(OpenApiFunctionalTags::loadOperations(self::root() . '/openapi-administration.json'));
		$union = $default + $administration;
		ksort($union);

		$this->assertSame([], OpenApiFunctionalTags::collectGoldenMapErrors($union));
	}

	public function testUnionCountsMatchApprovedTaxonomy(): void {
		$default = OpenApiFunctionalTags::indexById(OpenApiFunctionalTags::loadOperations(self::root() . '/openapi.json'));
		$administration = OpenApiFunctionalTags::indexById(OpenApiFunctionalTags::loadOperations(self::root() . '/openapi-administration.json'));
		$counts = OpenApiFunctionalTags::countTags(array_values($default + $administration));
		$expected = OpenApiFunctionalTags::EXPECTED_UNION_COUNTS;
		ksort($expected);

		$this->assertSame($expected, $counts);
		$this->assertSame(array_sum($expected), array_sum($counts));
	}

	public function testFullSpecificationOnlyOmitsKnownOperations(): void {
		$default = OpenApiFunctionalTags::indexById(OpenApiFunctionalTags::loadOperations(self::root() . '/openapi.json'));
		$administration = OpenApiFunctionalTags::indexById(OpenApiFunctionalTags::loadOperations(self::root() . '/openapi-administration.json'));
		$full = OpenApiFunctionalTags::indexById(OpenApiFunctionalTags::loadOperations(self::root() . '/openapi-full.json'));

		$this->assertSame(
			[],
			OpenApiFunctionalTags::collectFullSpecOmissions(array_keys($default + $administration), array_keys($full)),
		);
	}

	public function testApprovedTagProducesNoError(): void {
		$this->assertSame(
			[],
			OpenApiFunctionalTags::collectErrors('fixture', [self::operation(['signing'])]),
		);
	}

	public static function invalidOperationProvider(): array {
		return [
			'legacy tag' => [['notify'], 'legacy tag'],
			'missing tag' => [[], 'exactly one functional tag'],
			'multiple tags' => [['signing', 'requests'], 'exactly one functional tag'],
			'unapproved tag' => [['billing'], 'unclassified tag'],
		];
	}

	#[DataProvider('invalidOperationProvider')]
	public function testInvalidOperationIsReported(array $tags, string $needle): void {
		$errors = OpenApiFunctionalTags::collectErrors('fixture', [self::operation($tags)]);

		$this->assertNotSame([], $errors);
		$this->assertStringContainsString($needle, implode("\n", $errors));
	}

	public function testDuplicateOperationIdIsReported(): void {
		$errors = OpenApiFunctionalTags::collectErrors('fixture', [
			self::operation(['signing'], 'example-operation'),
			self::operation(['signing'], 'example-operation'),
		]);

		$this->assertNotSame([], $errors);
		$this->assertStringContainsString('Duplicate operationId example-operation', implode("\n", $errors));
	}

	public function testGoldenMapRejectsUnknownOperation(): void {
		$errors = OpenApiFunctionalTags::collectGoldenMapErrors([
			'not-a-real-operation' => self::operation(['signing'], 'not-a-real-operation'),
		]);

		$this->assertNotSame([], $errors);
		$this->assertStringContainsString('not-a-real-operation', implode("\n", $errors));
	}
}
