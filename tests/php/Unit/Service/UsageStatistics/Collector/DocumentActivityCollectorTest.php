<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\UsageStatistics\Collector;

use OCA\Libresign\Service\UsageStatistics\Collector\DocumentActivityCollector;
use OCA\Libresign\Service\UsageStatistics\Model\ReportMode;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use OCA\Libresign\Service\UsageStatistics\Source\UsageActivityReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentActivityCollectorTest extends TestCase {
	#[DataProvider('modesProvider')]
	public function testFilesAndEnvelopesAreCountedSeparatelyInEveryMode(ReportMode $mode): void {
		$period = ReportPeriod::monthContaining(new \DateTimeImmutable('2026-09-15T00:00:00Z'));
		$reader = $this->createMock(UsageActivityReader::class);
		$reader->expects($this->once())->method('countStandaloneFilesCreated')->with($period)->willReturn(7);
		$reader->expects($this->once())->method('countEnvelopesCreated')->with($period)->willReturn(0);

		$values = (new DocumentActivityCollector($reader))->collect($period, $mode);

		$this->assertSame(7, $values['documents.files_created']->value());
		$this->assertTrue($values['documents.envelopes_created']->isAvailable());
		$this->assertSame(0, $values['documents.envelopes_created']->value());
	}

	public static function modesProvider(): array {
		return [
			'current' => [ReportMode::CURRENT],
			'historical' => [ReportMode::HISTORICAL],
		];
	}
}
