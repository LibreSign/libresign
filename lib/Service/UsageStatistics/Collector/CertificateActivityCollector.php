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
 * Certificates are recorded only since LibreSign 12.1 (OpenSSL) and 12.2/13
 * (CFSSL); nothing issued before that can be counted. The counts are known
 * for a period when recording already covered the first LibreSign activity
 * (a fresh install creates its root CA first), or when the period starts
 * after the first recorded certificate (an upgraded instance).
 */
class CertificateActivityCollector implements IMetricCollector {
	public function __construct(
		private UsageActivityReader $reader,
	) {
	}

	#[\Override]
	public function metricIds(): array {
		return ['certificate.certificates_issued', 'certificate.certificates_revoked'];
	}

	#[\Override]
	public function collect(ReportPeriod $period, ReportMode $mode): array {
		if (!$this->isHistoryComplete($period)) {
			return [
				'certificate.certificates_issued' => MetricValue::unavailable(),
				'certificate.certificates_revoked' => MetricValue::unavailable(),
			];
		}
		return [
			'certificate.certificates_issued' => MetricValue::of($this->reader->countSigningCertificatesIssued($period)),
			'certificate.certificates_revoked' => MetricValue::of($this->reader->countSigningCertificatesRevoked($period)),
		];
	}

	private function isHistoryComplete(ReportPeriod $period): bool {
		$firstActivityAt = $this->reader->firstActivityAt();
		$firstCertificateAt = $this->reader->firstCertificateRecordedAt();
		if ($firstCertificateAt === null) {
			return $firstActivityAt === null;
		}
		if ($firstActivityAt === null || $firstCertificateAt <= $firstActivityAt) {
			return true;
		}
		return $period->start() >= $firstCertificateAt;
	}
}
