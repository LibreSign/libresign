<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics;

use OCA\Libresign\Service\UsageStatistics\Collector\CertificateActivityCollector;
use OCA\Libresign\Service\UsageStatistics\Collector\DocumentActivityCollector;
use OCA\Libresign\Service\UsageStatistics\Collector\EnvironmentCollector;
use OCA\Libresign\Service\UsageStatistics\Collector\LibreSignConfigurationCollector;
use OCA\Libresign\Service\UsageStatistics\Collector\SigningActivityCollector;

final class UsageStatisticsCollectors {
	/** @var list<class-string<Contract\IMetricCollector>> */
	public const ALL = [
		EnvironmentCollector::class,
		LibreSignConfigurationCollector::class,
		CertificateActivityCollector::class,
		DocumentActivityCollector::class,
		SigningActivityCollector::class,
	];
}
