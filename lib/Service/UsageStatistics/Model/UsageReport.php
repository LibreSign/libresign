<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics\Model;

final class UsageReport {
	/**
	 * @param list<CollectedMetric> $metrics
	 */
	public function __construct(
		public readonly string $application,
		public readonly int $schemaVersion,
		public readonly ReportPeriod $period,
		public readonly array $metrics,
	) {
	}
}
