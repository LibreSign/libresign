<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\UsageStatistics\Model;

use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportPeriodTest extends TestCase {
	#[DataProvider('instantsProvider')]
	public function testMonthContainingCoversTheWholeUtcMonth(string $instant, string $start, string $end): void {
		$period = ReportPeriod::monthContaining(new \DateTimeImmutable($instant));

		$this->assertSame($start, $period->start()->format(\DateTimeInterface::RFC3339));
		$this->assertSame($end, $period->end()->format(\DateTimeInterface::RFC3339));
	}

	public static function instantsProvider(): array {
		return [
			'middle of the month' => ['2026-08-15T10:00:00+00:00', '2026-08-01T00:00:00+00:00', '2026-09-01T00:00:00+00:00'],
			'first instant belongs to the month' => ['2026-08-01T00:00:00+00:00', '2026-08-01T00:00:00+00:00', '2026-09-01T00:00:00+00:00'],
			'last instant before the next month' => ['2026-08-31T23:59:59+00:00', '2026-08-01T00:00:00+00:00', '2026-09-01T00:00:00+00:00'],
			'local time is converted to UTC first' => ['2026-09-01T01:00:00+03:00', '2026-08-01T00:00:00+00:00', '2026-09-01T00:00:00+00:00'],
			'december rolls over the year' => ['2025-12-20T00:00:00+00:00', '2025-12-01T00:00:00+00:00', '2026-01-01T00:00:00+00:00'],
		];
	}

	public function testBoundariesAreStartInclusiveAndEndExclusive(): void {
		$period = ReportPeriod::monthContaining(new \DateTimeImmutable('2026-08-10T00:00:00Z'));

		$this->assertTrue($period->contains(new \DateTimeImmutable('2026-08-01T00:00:00Z')));
		$this->assertTrue($period->contains(new \DateTimeImmutable('2026-08-31T23:59:59Z')));
		$this->assertFalse($period->contains(new \DateTimeImmutable('2026-09-01T00:00:00Z')));
		$this->assertFalse($period->contains(new \DateTimeImmutable('2026-07-31T23:59:59Z')));
	}

	public function testNextReturnsTheFollowingMonth(): void {
		$period = ReportPeriod::monthContaining(new \DateTimeImmutable('2026-12-05T00:00:00Z'))->next();

		$this->assertSame('2027-01-01T00:00:00+00:00', $period->start()->format(\DateTimeInterface::RFC3339));
		$this->assertSame('2027-02-01T00:00:00+00:00', $period->end()->format(\DateTimeInterface::RFC3339));
	}
}
