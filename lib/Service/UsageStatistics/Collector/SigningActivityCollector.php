<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics\Collector;

use OCA\Libresign\Service\UsageStatistics\Contract\IMetricCollector;
use OCA\Libresign\Service\UsageStatistics\Model\MetricValue;
use OCA\Libresign\Service\UsageStatistics\Model\ReportMode;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use OCA\Libresign\Service\UsageStatistics\Source\UsageActivityReader;

/**
 * Rejection (LibreSign 15) and observers arrived together with the columns
 * that record them, so before them the counts are a known zero.
 */
class SigningActivityCollector implements IMetricCollector {
	public function __construct(
		private UsageActivityReader $reader,
	) {
	}

	#[\Override]
	public function metricIds(): array {
		return [
			'signatures.requests_created',
			'signatures.completed',
			'signatures.rejected',
			'participants.observers_added',
		];
	}

	#[\Override]
	public function collect(ReportPeriod $period, ReportMode $mode): array {
		return [
			'signatures.requests_created' => MetricValue::of($this->reader->countSigningRequestsCreated($period)),
			'signatures.completed' => MetricValue::of($this->reader->countSigningRequestsCompleted($period)),
			'signatures.rejected' => MetricValue::of($this->reader->countSigningRequestsRejected($period)),
			'participants.observers_added' => MetricValue::of($this->reader->countObserversAdded($period)),
		];
	}
}
