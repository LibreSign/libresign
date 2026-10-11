<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\UsageStatistics;

use OCA\Libresign\Service\UsageStatistics\Model\ReportMode;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use OCA\Libresign\Service\UsageStatistics\UsageReportBuilder;
use OCA\Libresign\Service\UsageStatistics\UsageStatisticsSchema;
use OCP\Server;

/**
 * @group DB
 */
final class UsageReportBuilderTest extends \OCA\Libresign\Tests\Integration\TestCase {
	public function testTheCurrentReportHasEveryMetricOfTheSchemaThatTheServerCanTell(): void {
		$report = Server::get(UsageReportBuilder::class)->build($this->period(), ReportMode::CURRENT);

		$reported = array_map(static fn ($metric): string => $metric->category . '.' . $metric->key, $report->metrics);
		$this->assertContains('nextcloud.version', $reported);
		$this->assertContains('database.type', $reported);
		$this->assertContains('database.version', $reported);
		$this->assertContains('signatures.requests_created', $reported);
		foreach (UsageStatisticsSchema::load()->definitions() as $definition) {
			if ($definition->required) {
				$this->assertContains($definition->id(), $reported);
			}
		}
	}

	public function testAHistoricalReportHasNoSnapshot(): void {
		$report = Server::get(UsageReportBuilder::class)->build($this->period(), ReportMode::HISTORICAL);

		$schema = UsageStatisticsSchema::load();
		foreach ($report->metrics as $metric) {
			$this->assertFalse($schema->get($metric->category . '.' . $metric->key)->isSnapshot());
		}
	}

	public function testBuildingAReportChangesNothing(): void {
		$appConfig = self::getMockAppConfig();
		$before = $appConfig->getAllValues('libresign');

		Server::get(UsageReportBuilder::class)->build($this->period(), ReportMode::CURRENT);

		$this->assertSame($before, $appConfig->getAllValues('libresign'));
	}

	private function period(): ReportPeriod {
		return ReportPeriod::monthContaining(new \DateTimeImmutable('2026-09-15T00:00:00Z'));
	}
}
