<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\UsageStatistics\Collector;

use OCA\Libresign\Service\UsageStatistics\Collector\CertificateActivityCollector;
use OCA\Libresign\Service\UsageStatistics\Model\ReportMode;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use OCA\Libresign\Service\UsageStatistics\Source\UsageActivityReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CertificateActivityCollectorTest extends TestCase {
	private UsageActivityReader&MockObject $reader;

	public function setUp(): void {
		$this->reader = $this->createMock(UsageActivityReader::class);
		$this->reader->method('countSigningCertificatesIssued')->willReturn(4);
		$this->reader->method('countSigningCertificatesRevoked')->willReturn(1);
	}

	#[DataProvider('availabilityProvider')]
	public function testCertificatesAreReportedOnlyWhenTheirHistoryIsComplete(
		?string $firstActivityAt,
		?string $firstCertificateAt,
		string $periodMonth,
		bool $available,
	): void {
		$this->reader->method('firstActivityAt')->willReturn($this->date($firstActivityAt));
		$this->reader->method('firstCertificateRecordedAt')->willReturn($this->date($firstCertificateAt));

		$values = (new CertificateActivityCollector($this->reader))->collect(
			ReportPeriod::monthContaining(new \DateTimeImmutable($periodMonth)),
			ReportMode::HISTORICAL,
		);

		$this->assertSame($available, $values['certificate.certificates_issued']->isAvailable());
		$this->assertSame($available, $values['certificate.certificates_revoked']->isAvailable());
		if ($available) {
			$this->assertSame(4, $values['certificate.certificates_issued']->value());
			$this->assertSame(1, $values['certificate.certificates_revoked']->value());
		}
	}

	public static function availabilityProvider(): array {
		return [
			'fresh install: the root CA is older than the first file' => [
				'2026-03-10T12:00:00Z', '2026-03-10T09:00:00Z', '2026-03-01T00:00:00Z', true,
			],
			'certificates recorded since the first activity, later month' => [
				'2026-03-10T12:00:00Z', '2026-03-10T12:00:00Z', '2026-07-01T00:00:00Z', true,
			],
			'upgraded instance: month before the first recorded certificate' => [
				'2024-01-05T00:00:00Z', '2025-10-28T15:00:00Z', '2025-09-01T00:00:00Z', false,
			],
			'upgraded instance: the month of the upgrade is incomplete' => [
				'2024-01-05T00:00:00Z', '2025-10-28T15:00:00Z', '2025-10-01T00:00:00Z', false,
			],
			'upgraded instance: first month that starts after the first record' => [
				'2024-01-05T00:00:00Z', '2025-10-28T15:00:00Z', '2025-11-01T00:00:00Z', true,
			],
			'activity but no certificate recorded at all' => [
				'2024-01-05T00:00:00Z', null, '2026-08-01T00:00:00Z', false,
			],
			'nothing recorded yet: a known zero' => [
				null, null, '2026-08-01T00:00:00Z', true,
			],
		];
	}

	public function testTheCurrentReportUsesTheSameRule(): void {
		$this->reader->method('firstActivityAt')->willReturn($this->date('2024-01-05T00:00:00Z'));
		$this->reader->method('firstCertificateRecordedAt')->willReturn($this->date('2025-10-28T15:00:00Z'));

		$values = (new CertificateActivityCollector($this->reader))->collect(
			ReportPeriod::monthContaining(new \DateTimeImmutable('2026-08-01T00:00:00Z')),
			ReportMode::CURRENT,
		);

		$this->assertSame(4, $values['certificate.certificates_issued']->value());
	}

	private function date(?string $value): ?\DateTimeImmutable {
		return $value === null ? null : new \DateTimeImmutable($value);
	}
}
