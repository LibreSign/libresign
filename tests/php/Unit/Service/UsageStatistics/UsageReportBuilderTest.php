<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\UsageStatistics;

use OCA\Libresign\Service\UsageStatistics\Contract\IMetricCollector;
use OCA\Libresign\Service\UsageStatistics\Model\MetricValue;
use OCA\Libresign\Service\UsageStatistics\Model\ReportMode;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use OCA\Libresign\Service\UsageStatistics\Source\UsageActivityReader;
use OCA\Libresign\Service\UsageStatistics\UsageReportBuilder;
use OCA\Libresign\Service\UsageStatistics\UsageStatisticsCollectors;
use OCA\Libresign\Service\UsageStatistics\UsageStatisticsException;
use OCA\Libresign\Service\UsageStatistics\UsageStatisticsSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class UsageReportBuilderTest extends TestCase {
	private UsageActivityReader&MockObject $activityReader;

	public function setUp(): void {
		$this->activityReader = $this->createMock(UsageActivityReader::class);
	}

	public function testEveryShippedSchemaMetricHasExactlyOneCollector(): void {
		$owners = [];
		foreach (UsageStatisticsCollectors::ALL as $collectorClass) {
			$collector = (new \ReflectionClass($collectorClass))->newInstanceWithoutConstructor();
			foreach ($collector->metricIds() as $metricId) {
				$this->assertArrayNotHasKey($metricId, $owners, $metricId . ' has more than one collector');
				$owners[$metricId] = $collectorClass;
			}
		}

		$schemaIds = array_map(
			static fn ($definition): string => $definition->id(),
			UsageStatisticsSchema::load()->definitions(),
		);
		sort($schemaIds);
		$collectedIds = array_keys($owners);
		sort($collectedIds);
		$this->assertSame($schemaIds, $collectedIds);
	}

	public function testBuildsTheMetricsInSchemaOrder(): void {
		$builder = $this->builder($this->schema(), [
			$this->collector(['documents.files_created' => MetricValue::of(3), 'php.version' => MetricValue::of('8.3')]),
		]);

		$report = $builder->build($this->period(), ReportMode::CURRENT);

		$this->assertSame('libresign', $report->application);
		$this->assertSame(1, $report->schemaVersion);
		$this->assertSame([
			['category' => 'php', 'key' => 'version', 'type' => 'string', 'value' => '8.3'],
			['category' => 'documents', 'key' => 'files_created', 'type' => 'integer', 'value' => 3],
		], array_map(static fn ($metric): array => $metric->toArray(), $report->metrics));
	}

	public function testAZeroCounterIsReportedAsAKnownZero(): void {
		$builder = $this->builder($this->schema(), [
			$this->collector(['documents.files_created' => MetricValue::of(0), 'php.version' => MetricValue::unavailable()]),
		]);

		$report = $builder->build($this->period(), ReportMode::HISTORICAL);

		$this->assertCount(1, $report->metrics);
		$this->assertSame(0, $report->metrics[0]->value);
	}

	public function testAnUnavailableOptionalMetricIsOmitted(): void {
		$builder = $this->builder($this->schema(), [
			$this->collector(['documents.files_created' => MetricValue::of(1), 'php.version' => MetricValue::unavailable()]),
		]);

		$report = $builder->build($this->period(), ReportMode::CURRENT);

		$this->assertSame(['files_created'], array_map(static fn ($metric): string => $metric->key, $report->metrics));
	}

	public function testAHistoricalReportNeverCarriesASnapshot(): void {
		$builder = $this->builder($this->schema(), [
			$this->collector(['documents.files_created' => MetricValue::of(1), 'php.version' => MetricValue::of('8.3')]),
		]);

		$this->expectException(UsageStatisticsException::class);
		$this->expectExceptionMessage('php.version');

		$builder->build($this->period(), ReportMode::HISTORICAL);
	}

	#[DataProvider('unsafeCollectorAnswersProvider')]
	public function testAnUnsafeCollectorAnswerFailsTheWholeReport(array $answers): void {
		$builder = $this->builder($this->schema(), [$this->collector($answers, ['documents.files_created', 'php.version'])]);

		$this->expectException(UsageStatisticsException::class);

		$builder->build($this->period(), ReportMode::CURRENT);
	}

	public static function unsafeCollectorAnswersProvider(): array {
		$string = MetricValue::of('8.3');
		return [
			'a metric is not answered' => [['documents.files_created' => MetricValue::of(1)]],
			'an unexpected metric is answered' => [['documents.files_created' => MetricValue::of(1), 'php.version' => $string, 'php.extra' => $string]],
			'a required metric is unavailable' => [['documents.files_created' => MetricValue::unavailable(), 'php.version' => $string]],
			'an integer is a string' => [['documents.files_created' => MetricValue::of('1'), 'php.version' => $string]],
			'an integer is negative' => [['documents.files_created' => MetricValue::of(-1), 'php.version' => $string]],
			'a string is an integer' => [['documents.files_created' => MetricValue::of(1), 'php.version' => MetricValue::of(8)]],
			'a string is empty' => [['documents.files_created' => MetricValue::of(1), 'php.version' => MetricValue::of('')]],
			'a string is too long' => [['documents.files_created' => MetricValue::of(1), 'php.version' => MetricValue::of(str_repeat('a', 1025))]],
			'a string is not UTF-8' => [['documents.files_created' => MetricValue::of(1), 'php.version' => MetricValue::of("\xff")]],
			'an answer is not a metric value' => [['documents.files_created' => 1, 'php.version' => $string]],
		];
	}

	public function testASchemaMetricWithoutCollectorIsRejected(): void {
		$builder = $this->builder($this->schema(), [$this->collector(['documents.files_created' => MetricValue::of(1)])]);

		$this->expectException(UsageStatisticsException::class);
		$this->expectExceptionMessage('php.version');

		$builder->build($this->period(), ReportMode::CURRENT);
	}

	public function testAMetricWithTwoCollectorsIsRejected(): void {
		$builder = $this->builder($this->schema(), [
			$this->collector(['documents.files_created' => MetricValue::of(1), 'php.version' => MetricValue::of('8.3')]),
			$this->collector(['php.version' => MetricValue::of('8.3')]),
		]);

		$this->expectException(UsageStatisticsException::class);
		$this->expectExceptionMessage('php.version');

		$builder->build($this->period(), ReportMode::CURRENT);
	}

	public function testACollectorMetricOutsideTheSchemaIsRejected(): void {
		$builder = $this->builder($this->schema(), [
			$this->collector(['documents.files_created' => MetricValue::of(1), 'php.version' => MetricValue::of('8.3'), 'php.extensions' => MetricValue::of(3)]),
		]);

		$this->expectException(UsageStatisticsException::class);
		$this->expectExceptionMessage('php.extensions');

		$builder->build($this->period(), ReportMode::CURRENT);
	}

	public function testAReportWithoutAnyMetricIsRejected(): void {
		$schema = UsageStatisticsSchema::fromArray([
			'application' => 'libresign',
			'schemaVersion' => 1,
			'metrics' => [$this->metric('php', 'version', 'string', 'snapshot', 'distribution', false)],
		]);
		$builder = $this->builder($schema, [$this->collector(['php.version' => MetricValue::unavailable()])]);

		$this->expectException(UsageStatisticsException::class);

		$builder->build($this->period(), ReportMode::HISTORICAL);
	}

	public function testTheFirstActivityPeriodIsTheMonthOfTheFirstLibreSignFile(): void {
		$this->activityReader->method('firstActivityAt')->willReturn(new \DateTimeImmutable('2025-03-17T08:00:00Z'));

		$period = $this->builder($this->schema(), [])->firstActivityPeriod();

		$this->assertSame('2025-03-01T00:00:00+00:00', $period?->start()->format(\DateTimeInterface::RFC3339));
	}

	public function testThereIsNoFirstActivityPeriodBeforeAnyActivity(): void {
		$this->activityReader->method('firstActivityAt')->willReturn(null);

		$this->assertNull($this->builder($this->schema(), [])->firstActivityPeriod());
	}

	private function period(): ReportPeriod {
		return ReportPeriod::monthContaining(new \DateTimeImmutable('2026-08-10T00:00:00Z'));
	}

	private function schema(): UsageStatisticsSchema {
		return UsageStatisticsSchema::fromArray([
			'application' => 'libresign',
			'schemaVersion' => 1,
			'metrics' => [
				$this->metric('php', 'version', 'string', 'snapshot', 'distribution', false),
				$this->metric('documents', 'files_created', 'integer', 'period', 'numerical', true),
			],
		]);
	}

	private function metric(string $category, string $key, string $type, string $kind, string $aggregation, bool $required): array {
		return [
			'category' => $category,
			'key' => $key,
			'type' => $type,
			'kind' => $kind,
			'aggregation' => $aggregation,
			'description' => $key,
			'required' => $required,
		];
	}

	/**
	 * @param array<string, mixed> $answers
	 * @param list<string>|null $metricIds defaults to the answered ids
	 */
	private function collector(array $answers, ?array $metricIds = null): IMetricCollector {
		return new class($answers, $metricIds ?? array_keys($answers)) implements IMetricCollector {
			public function __construct(
				private array $answers,
				private array $metricIds,
			) {
			}

			public function metricIds(): array {
				return $this->metricIds;
			}

			public function collect(ReportPeriod $period, ReportMode $mode): array {
				return $this->answers;
			}
		};
	}

	/**
	 * @param list<IMetricCollector> $collectors
	 */
	private function builder(UsageStatisticsSchema $schema, array $collectors): UsageReportBuilder {
		$byClass = [];
		foreach ($collectors as $index => $collector) {
			$byClass['collector' . $index] = $collector;
		}
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(static fn (string $id) => $byClass[$id]);

		return new UsageReportBuilder($container, $this->activityReader, $schema, array_keys($byClass));
	}
}
