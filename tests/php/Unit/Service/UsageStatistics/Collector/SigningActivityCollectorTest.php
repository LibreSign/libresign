<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\UsageStatistics\Collector;

use OCA\Libresign\Service\UsageStatistics\Collector\SigningActivityCollector;
use OCA\Libresign\Service\UsageStatistics\Model\ReportMode;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use OCA\Libresign\Service\UsageStatistics\Source\UsageActivityReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SigningActivityCollectorTest extends TestCase {
	#[DataProvider('modesProvider')]
	public function testEachSigningMetricComesFromItsOwnEvent(ReportMode $mode): void {
		$period = ReportPeriod::monthContaining(new \DateTimeImmutable('2026-09-15T00:00:00Z'));
		$reader = $this->createMock(UsageActivityReader::class);
		$reader->method('countSigningRequestsCreated')->with($period)->willReturn(5);
		$reader->method('countSigningRequestsCompleted')->with($period)->willReturn(4);
		$reader->method('countSigningRequestsRejected')->with($period)->willReturn(0);
		$reader->method('countObserversAdded')->with($period)->willReturn(2);

		$values = (new SigningActivityCollector($reader))->collect($period, $mode);

		$this->assertSame([
			'signatures.requests_created' => 5,
			'signatures.completed' => 4,
			'signatures.rejected' => 0,
			'participants.observers_added' => 2,
		], array_map(static fn ($value) => $value->value(), $values));
	}

	public static function modesProvider(): array {
		return [
			'current' => [ReportMode::CURRENT],
			'historical' => [ReportMode::HISTORICAL],
		];
	}
}
