<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\UsageStatistics\Model\MetricDefinition;

/**
 * The versioned schema shipped in appinfo/usage-statistics/. The checks
 * mirror the usage statistics server, so a schema it would refuse fails here.
 */
final class UsageStatisticsSchema {
	public const VERSION = 1;

	private const NAME_PATTERN = '/^[A-Za-z0-9_.:-]+$/D';
	private const METRIC_FIELDS = ['category', 'key', 'type', 'kind', 'aggregation', 'description', 'required'];
	private const TYPES = ['integer', 'number', 'boolean', 'string'];
	private const KINDS = ['snapshot', 'period', 'counter', 'categorical'];
	private const AGGREGATIONS = ['distribution', 'numerical', 'none'];
	private const MAX_METRICS = 256;
	private const MAX_DESCRIPTION_BYTES = 512;

	private readonly string $application;
	private readonly int $version;
	/** @var array<string, MetricDefinition> */
	private array $definitions = [];

	/**
	 * @param array<mixed>|null $data a decoded schema; null reads the one shipped with the app
	 */
	public function __construct(?array $data = null) {
		$data ??= self::readShippedSchema();

		$unknownFields = array_diff(array_keys($data), ['application', 'schemaVersion', 'metrics']);
		if ($unknownFields !== []) {
			throw new UsageStatisticsException('Unknown schema field: ' . implode(', ', $unknownFields));
		}
		if (($data['application'] ?? null) !== Application::APP_ID) {
			throw new UsageStatisticsException('Schema application must be ' . Application::APP_ID);
		}
		$version = $data['schemaVersion'] ?? null;
		if (!is_int($version) || $version < 1) {
			throw new UsageStatisticsException('Schema version must be a positive integer');
		}
		$metrics = $data['metrics'] ?? null;
		if (!is_array($metrics) || !array_is_list($metrics) || $metrics === [] || count($metrics) > self::MAX_METRICS) {
			throw new UsageStatisticsException('Schema must define between 1 and ' . self::MAX_METRICS . ' metrics');
		}

		foreach ($metrics as $metric) {
			$definition = self::parseMetric($metric);
			if (isset($this->definitions[$definition->id()])) {
				throw new UsageStatisticsException('Duplicate metric: ' . $definition->id());
			}
			$this->definitions[$definition->id()] = $definition;
		}
		$this->application = Application::APP_ID;
		$this->version = $version;
	}

	public static function load(): self {
		return new self();
	}

	public static function fromArray(array $data): self {
		return new self($data);
	}

	private static function readShippedSchema(): array {
		$path = dirname(__DIR__, 3) . '/appinfo/usage-statistics/schema-v' . self::VERSION . '.json';
		$content = is_readable($path) ? file_get_contents($path) : false;
		if ($content === false) {
			throw new UsageStatisticsException('Usage statistics schema not found: ' . $path);
		}
		try {
			$data = json_decode($content, true, 16, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new UsageStatisticsException('Usage statistics schema is not valid JSON', 0, $e);
		}
		if (!is_array($data)) {
			throw new UsageStatisticsException('Usage statistics schema must be a JSON object');
		}
		return $data;
	}

	private static function parseMetric(mixed $metric): MetricDefinition {
		if (!is_array($metric)) {
			throw new UsageStatisticsException('Each metric must be an object');
		}
		$fields = array_keys($metric);
		sort($fields);
		$expected = self::METRIC_FIELDS;
		sort($expected);
		if ($fields !== $expected) {
			throw new UsageStatisticsException('A metric must have exactly the fields: ' . implode(', ', self::METRIC_FIELDS));
		}
		foreach (['category', 'key'] as $name) {
			if (!is_string($metric[$name]) || preg_match(self::NAME_PATTERN, $metric[$name]) !== 1) {
				throw new UsageStatisticsException('Invalid metric ' . $name);
			}
		}
		if (!in_array($metric['type'], self::TYPES, true)
			|| !in_array($metric['kind'], self::KINDS, true)
			|| !in_array($metric['aggregation'], self::AGGREGATIONS, true)) {
			throw new UsageStatisticsException('Invalid type, kind or aggregation for ' . $metric['category'] . '.' . $metric['key']);
		}
		if ($metric['aggregation'] === 'numerical' && !in_array($metric['type'], ['integer', 'number'], true)) {
			throw new UsageStatisticsException('Numerical aggregation requires a numeric type');
		}
		if (!is_string($metric['description']) || strlen($metric['description']) > self::MAX_DESCRIPTION_BYTES) {
			throw new UsageStatisticsException('Invalid metric description');
		}
		if (!is_bool($metric['required'])) {
			throw new UsageStatisticsException('Metric required flag must be a boolean');
		}

		return new MetricDefinition(
			$metric['category'],
			$metric['key'],
			$metric['type'],
			$metric['kind'],
			$metric['aggregation'],
			$metric['description'],
			$metric['required'],
		);
	}

	public function application(): string {
		return $this->application;
	}

	public function version(): int {
		return $this->version;
	}

	/** @return list<MetricDefinition> */
	public function definitions(): array {
		return array_values($this->definitions);
	}

	public function get(string $metricId): MetricDefinition {
		return $this->definitions[$metricId]
			?? throw new UsageStatisticsException('Unknown usage statistics metric: ' . $metricId);
	}
}
