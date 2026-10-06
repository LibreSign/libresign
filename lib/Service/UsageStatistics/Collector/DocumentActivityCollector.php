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
 * Files are counted from the first install. Envelopes did not exist before
 * LibreSign 12.2/13, so an earlier period has a known zero envelopes.
 */
class DocumentActivityCollector implements IMetricCollector {
	public function __construct(
		private UsageActivityReader $reader,
	) {
	}

	#[\Override]
	public function metricIds(): array {
		return ['documents.files_created', 'documents.envelopes_created'];
	}

	#[\Override]
	public function collect(ReportPeriod $period, ReportMode $mode): array {
		return [
			'documents.files_created' => MetricValue::of($this->reader->countStandaloneFilesCreated($period)),
			'documents.envelopes_created' => MetricValue::of($this->reader->countEnvelopesCreated($period)),
		];
	}
}
