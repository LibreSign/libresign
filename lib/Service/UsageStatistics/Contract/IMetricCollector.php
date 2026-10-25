<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics\Contract;

use OCA\Libresign\Service\UsageStatistics\Model\MetricValue;
use OCA\Libresign\Service\UsageStatistics\Model\ReportMode;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;

interface IMetricCollector {
	/**
	 * Schema metrics this collector is the only source of, as "category.key".
	 *
	 * @return list<string>
	 */
	public function metricIds(): array;

	/**
	 * Must answer every id from metricIds(), using MetricValue::unavailable()
	 * when the value cannot be known for the period.
	 *
	 * @return array<string, MetricValue>
	 */
	public function collect(ReportPeriod $period, ReportMode $mode): array;
}
