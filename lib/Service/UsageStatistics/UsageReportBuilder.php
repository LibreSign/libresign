<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics;

use OCA\Libresign\Service\UsageStatistics\Contract\IMetricCollector;
use OCA\Libresign\Service\UsageStatistics\Model\CollectedMetric;
use OCA\Libresign\Service\UsageStatistics\Model\MetricDefinition;
use OCA\Libresign\Service\UsageStatistics\Model\MetricValue;
use OCA\Libresign\Service\UsageStatistics\Model\ReportMode;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use OCA\Libresign\Service\UsageStatistics\Model\UsageReport;
use OCA\Libresign\Service\UsageStatistics\Source\UsageActivityReader;
use Psr\Container\ContainerInterface;

/**
 * Builds the usage report of one monthly period without side effects, so the
 * same report can be previewed, persisted and submitted.
 *
 * A metric is reported with its value, including a known zero, or omitted
 * when its collector states that it cannot be known for the period. Anything
 * else fails the whole report: a missing answer, an invalid value, a required
 * metric without value, or a current snapshot claimed for a past period.
 */
class UsageReportBuilder {
	private const MAX_STRING_BYTES = 1024;

	/**
	 * @param list<string> $collectorClasses
	 */
	public function __construct(
		private ContainerInterface $container,
		private UsageActivityReader $activityReader,
		private UsageStatisticsSchema $schema,
		private array $collectorClasses = UsageStatisticsCollectors::ALL,
	) {
	}

	public function build(ReportPeriod $period, ReportMode $mode): UsageReport {
		$values = [];
		foreach ($this->collectorsByMetric() as $collector) {
			$answers = $collector->collect($period, $mode);
			$expected = $collector->metricIds();
			$missing = array_diff($expected, array_keys($answers));
			$unexpected = array_diff(array_keys($answers), $expected);
			if ($missing !== [] || $unexpected !== []) {
				throw new UsageStatisticsException(sprintf(
					'Collector %s answered unexpected metrics (missing: %s; unexpected: %s)',
					$collector::class,
					implode(', ', $missing),
					implode(', ', $unexpected),
				));
			}
			$values += $answers;
		}

		$metrics = [];
		foreach ($this->schema->definitions() as $definition) {
			$value = $values[$definition->id()];
			if (!$value instanceof MetricValue) {
				throw new UsageStatisticsException('Invalid answer for ' . $definition->id());
			}
			$metric = $this->toCollectedMetric($definition, $value, $mode);
			if ($metric !== null) {
				$metrics[] = $metric;
			}
		}

		if ($metrics === []) {
			throw new UsageStatisticsException('A usage report needs at least one metric');
		}

		return new UsageReport($this->schema->application(), $this->schema->version(), $period, $metrics);
	}

	/** The month of the first LibreSign activity, where the historical series starts. */
	public function firstActivityPeriod(): ?ReportPeriod {
		$firstActivityAt = $this->activityReader->firstActivityAt();
		return $firstActivityAt === null ? null : ReportPeriod::monthContaining($firstActivityAt);
	}

	/**
	 * Resolves the collectors and checks that every schema metric has exactly one.
	 *
	 * @return array<string, IMetricCollector> keyed by collector class
	 */
	private function collectorsByMetric(): array {
		$collectors = [];
		$owners = [];
		foreach ($this->collectorClasses as $collectorClass) {
			$collector = $this->container->get($collectorClass);
			if (!$collector instanceof IMetricCollector) {
				throw new UsageStatisticsException('Invalid usage statistics collector: ' . $collectorClass);
			}
			foreach ($collector->metricIds() as $metricId) {
				$this->schema->get($metricId);
				if (isset($owners[$metricId])) {
					throw new UsageStatisticsException('Metric has more than one collector: ' . $metricId);
				}
				$owners[$metricId] = $collectorClass;
			}
			$collectors[$collectorClass] = $collector;
		}

		foreach ($this->schema->definitions() as $definition) {
			if (!isset($owners[$definition->id()])) {
				throw new UsageStatisticsException('Metric has no collector: ' . $definition->id());
			}
		}

		return $collectors;
	}

	private function toCollectedMetric(MetricDefinition $definition, MetricValue $value, ReportMode $mode): ?CollectedMetric {
		if (!$value->isAvailable()) {
			if ($definition->required) {
				throw new UsageStatisticsException('Required metric is unavailable: ' . $definition->id());
			}
			return null;
		}
		if ($mode === ReportMode::HISTORICAL && $definition->isSnapshot()) {
			throw new UsageStatisticsException('A current snapshot cannot be reported for a past period: ' . $definition->id());
		}

		$raw = $value->value();
		$valid = match ($definition->type) {
			'integer' => is_int($raw) && $raw >= 0,
			'boolean' => is_bool($raw),
			'string' => is_string($raw) && $raw !== '' && strlen($raw) <= self::MAX_STRING_BYTES && mb_check_encoding($raw, 'UTF-8'),
			default => false,
		};
		if (!$valid || $raw === null) {
			throw new UsageStatisticsException('Invalid value for ' . $definition->id());
		}

		return new CollectedMetric($definition->category, $definition->key, $definition->type, $raw);
	}
}
