<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\UsageStatistics;

use OCA\Libresign\Service\UsageStatistics\UsageStatisticsException;
use OCA\Libresign\Service\UsageStatistics\UsageStatisticsSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsageStatisticsSchemaTest extends TestCase {
	public function testTheShippedSchemaIsValid(): void {
		$schema = UsageStatisticsSchema::load();

		$this->assertSame('libresign', $schema->application());
		$this->assertSame(1, $schema->version());
		$this->assertNotEmpty($schema->definitions());
	}

	public function testTheShippedSchemaFollowsTheFixedRulesOfVersionOne(): void {
		foreach (UsageStatisticsSchema::load()->definitions() as $definition) {
			if ($definition->isSnapshot()) {
				$expectedAggregation = $definition->type === 'integer' ? 'numerical' : 'distribution';
				$this->assertSame($expectedAggregation, $definition->aggregation, $definition->id());
				$this->assertFalse($definition->required, $definition->id() . ' must be optional: historical reports omit snapshots');
				continue;
			}
			$this->assertSame('period', $definition->kind, $definition->id());
			$this->assertSame('integer', $definition->type, $definition->id());
			$this->assertSame('numerical', $definition->aggregation, $definition->id());
		}
	}

	public function testTheShippedSchemaHasTheCategoriesOfTheIssue(): void {
		$categories = array_values(array_unique(array_map(
			static fn ($definition): string => $definition->category,
			UsageStatisticsSchema::load()->definitions(),
		)));

		$this->assertSame(
			['nextcloud', 'php', 'database', 'system', 'libresign', 'certificate', 'documents', 'signatures', 'participants'],
			$categories,
		);
	}

	#[DataProvider('invalidSchemasProvider')]
	public function testInvalidSchemasAreRejected(array $schema): void {
		$this->expectException(UsageStatisticsException::class);

		UsageStatisticsSchema::fromArray($schema);
	}

	public static function invalidSchemasProvider(): array {
		$metric = [
			'category' => 'documents',
			'key' => 'files_created',
			'type' => 'integer',
			'kind' => 'period',
			'aggregation' => 'numerical',
			'description' => 'Files',
			'required' => true,
		];
		$schema = static fn (array $metrics, array $extra = []): array => array_merge(
			['application' => 'libresign', 'schemaVersion' => 1, 'metrics' => $metrics],
			$extra,
		);
		return [
			'unknown top-level field' => [$schema([$metric], ['source' => 'x'])],
			'unknown metric field' => [$schema([array_merge($metric, ['source' => 'SignRequest.createdAt'])])],
			'missing metric field' => [$schema([array_diff_key($metric, ['kind' => true])])],
			'no metrics' => [$schema([])],
			'wrong application' => [array_merge($schema([$metric]), ['application' => 'other'])],
			'schema version below one' => [array_merge($schema([$metric]), ['schemaVersion' => 0])],
			'key with a slash' => [$schema([array_merge($metric, ['key' => 'files/created'])])],
			'unknown type' => [$schema([array_merge($metric, ['type' => 'float'])])],
			'unknown kind' => [$schema([array_merge($metric, ['kind' => 'gauge'])])],
			'numerical string' => [$schema([array_merge($metric, ['type' => 'string', 'kind' => 'snapshot'])])],
			'required is not a boolean' => [$schema([array_merge($metric, ['required' => 'yes'])])],
			'description too long' => [$schema([array_merge($metric, ['description' => str_repeat('a', 513)])])],
			'duplicate metric' => [$schema([$metric, $metric])],
		];
	}

	public function testDefinitionsAreIndexedByCategoryAndKey(): void {
		$definition = UsageStatisticsSchema::load()->get('signatures.completed');

		$this->assertSame('signatures', $definition->category);
		$this->assertSame('completed', $definition->key);
	}

	public function testAnUnknownMetricIsRejected(): void {
		$this->expectException(UsageStatisticsException::class);

		UsageStatisticsSchema::load()->get('signatures.unknown');
	}
}
