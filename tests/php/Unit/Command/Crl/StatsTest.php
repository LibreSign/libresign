<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Command\Crl;

use OCA\Libresign\Command\Crl\Stats;
use OCA\Libresign\Service\Crl\CrlService;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class StatsTest extends TestCase {
	private CrlService&MockObject $crlService;
	private Stats $command;

	public function setUp(): void {
		parent::setUp();
		$this->crlService = $this->createMock(CrlService::class);
		$this->command = new Stats($this->crlService);
	}

	public function testDisplaysBasicStatistics(): void {
		$stats = [
			'issued' => 100,
			'revoked' => 10,
			'expired' => 5,
		];

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->once())
			->method('getRevocationStatistics')
			->willReturn([]);
		$this->crlService->expects($this->once())
			->method('getRevokedCertificates')
			->willReturn([]);

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('LibreSign CRL Statistics', $output);
		$this->assertStringContainsString('Database Statistics', $output);
		$this->assertStringContainsString('Total Certificates', $output);
		$this->assertStringContainsString('115', $output); // 100 + 10 + 5
		$this->assertStringContainsString('Valid Certificates', $output);
		$this->assertStringContainsString('100', $output);
		$this->assertStringContainsString('Revoked Certificates', $output);
		$this->assertStringContainsString('10', $output);
		$this->assertStringContainsString('Expired Certificates', $output);
		$this->assertStringContainsString('5', $output);
	}

	public function testDisplaysEmptyStatistics(): void {
		$stats = [
			'issued' => 0,
			'revoked' => 0,
			'expired' => 0,
		];

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->once())
			->method('getRevocationStatistics')
			->willReturn([]);
		$this->crlService->expects($this->once())
			->method('getRevokedCertificates')
			->willReturn([]);

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Total Certificates', $output);
		$this->assertStringContainsString('0', $output);
	}

	public function testDisplaysRevocationStatistics(): void {
		$stats = [
			'issued' => 50,
			'revoked' => 10,
			'expired' => 5,
		];
		$revocationStats = [
			[
				'reason_code' => 1,
				'description' => 'Key Compromise',
				'count' => 5,
			],
			[
				'reason_code' => 3,
				'description' => 'Affiliation Changed',
				'count' => 3,
			],
			[
				'reason_code' => 5,
				'description' => 'Superseded',
				'count' => 2,
			],
		];

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->once())
			->method('getRevocationStatistics')
			->willReturn($revocationStats);
		$this->crlService->expects($this->once())
			->method('getRevokedCertificates')
			->willReturn([]);

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Revocation Statistics', $output);
		$this->assertStringContainsString('Key Compromise', $output);
		$this->assertStringContainsString('5', $output);
		$this->assertStringContainsString('Affiliation Changed', $output);
		$this->assertStringContainsString('3', $output);
		$this->assertStringContainsString('Superseded', $output);
		$this->assertStringContainsString('2', $output);
	}

	public function testDisplaysRecentRevocations(): void {
		$stats = [
			'issued' => 50,
			'revoked' => 3,
			'expired' => 0,
		];
		$revokedCertificates = [
			[
				'serial_number' => 'ABC123',
				'description' => 'Key Compromise',
				'revoked_at' => '2025-01-15 10:30:00',
				'revoked_by' => 'admin',
			],
			[
				'serial_number' => 'DEF456',
				'description' => 'Superseded',
				'revoked_at' => '2025-01-10 14:20:00',
				'revoked_by' => 'admin',
			],
			[
				'serial_number' => 'GHI789',
				'description' => null,
				'revoked_at' => '2025-01-05 09:15:00',
				'revoked_by' => 'system',
			],
		];

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->once())
			->method('getRevocationStatistics')
			->willReturn([]);
		$this->crlService->expects($this->once())
			->method('getRevokedCertificates')
			->willReturn($revokedCertificates);

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Recent Revocations (Last 10)', $output);
		$this->assertStringContainsString('ABC123', $output);
		$this->assertStringContainsString('DEF456', $output);
		$this->assertStringContainsString('GHI789', $output);
		$this->assertStringContainsString('2025-01-15 10:30:00', $output);
		$this->assertStringContainsString('2025-01-10 14:20:00', $output);
		$this->assertStringContainsString('Key Compromise', $output);
		$this->assertStringContainsString('Superseded', $output);
	}

	public function testDisplaysOnlyFirst10RecentRevocations(): void {
		$stats = [
			'issued' => 50,
			'revoked' => 15,
			'expired' => 0,
		];
		$revokedCertificates = array_map(
			fn ($i) => [
				'serial_number' => 'SN' . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
				'description' => 'Reason ' . $i,
				'revoked_at' => '2025-01-' . str_pad((string)($i % 31), 2, '0', STR_PAD_LEFT) . ' 10:00:00',
				'revoked_by' => 'admin',
			],
			range(1, 15)
		);

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->once())
			->method('getRevocationStatistics')
			->willReturn([]);
		$this->crlService->expects($this->once())
			->method('getRevokedCertificates')
			->willReturn($revokedCertificates);

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Recent Revocations (Last 10)', $output);
		// Check that only the last 10 are displayed (reversed order)
		for ($i = 15; $i > 5; $i--) {
			$this->assertStringContainsString('SN' . str_pad((string)$i, 3, '0', STR_PAD_LEFT), $output);
		}
		// The first 5 should not be displayed
		$this->assertStringNotContainsString('SN001', $output);
		$this->assertStringNotContainsString('SN005', $output);
	}

	public function testHandlesEmptyRevocationStatistics(): void {
		$stats = [
			'issued' => 50,
			'revoked' => 0,
			'expired' => 0,
		];

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->once())
			->method('getRevocationStatistics')
			->willReturn([]);
		$this->crlService->expects($this->once())
			->method('getRevokedCertificates')
			->willReturn([]);

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Database Statistics', $output);
		// Revocation Statistics section should not appear if empty
		$this->assertStringNotContainsString('Revocation Statistics', $output);
	}

	public function testHandlesPartialStatistics(): void {
		$stats = [
			'issued' => 50,
			// missing 'revoked' and 'expired' keys
		];

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->once())
			->method('getRevocationStatistics')
			->willReturn([]);
		$this->crlService->expects($this->once())
			->method('getRevokedCertificates')
			->willReturn([]);

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Database Statistics', $output);
		$this->assertStringContainsString('Total Certificates', $output);
		// Should handle missing keys gracefully
		$this->assertStringContainsString('50', $output);
	}

	public function testHandlesStatisticsWithNullDescription(): void {
		$stats = [
			'issued' => 50,
			'revoked' => 1,
			'expired' => 0,
		];
		$revokedCertificates = [
			[
				'serial_number' => 'ABC123',
				'description' => null,
				'revoked_at' => '2025-01-15 10:30:00',
				'revoked_by' => 'admin',
			],
		];

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->once())
			->method('getRevocationStatistics')
			->willReturn([]);
		$this->crlService->expects($this->once())
			->method('getRevokedCertificates')
			->willReturn($revokedCertificates);

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('ABC123', $output);
		$this->assertStringContainsString('N/A', $output); // null description should show as N/A
	}

	public function testHandlesLargeNumbers(): void {
		$stats = [
			'issued' => 10000,
			'revoked' => 5000,
			'expired' => 2000,
		];

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->once())
			->method('getRevocationStatistics')
			->willReturn([]);
		$this->crlService->expects($this->once())
			->method('getRevokedCertificates')
			->willReturn([]);

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('17000', $output); // 10000 + 5000 + 2000
		$this->assertStringContainsString('10000', $output);
		$this->assertStringContainsString('5000', $output);
		$this->assertStringContainsString('2000', $output);
	}

	public function testHandlesGetStatisticsException(): void {
		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willThrowException(new \RuntimeException('Database error'));
		$this->crlService->expects($this->never())
			->method('getRevocationStatistics');
		$this->crlService->expects($this->never())
			->method('getRevokedCertificates');

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString('Error', $output);
		$this->assertStringContainsString('Database error', $output);
	}

	public function testHandlesGetRevocationStatisticsException(): void {
		$stats = [
			'issued' => 50,
			'revoked' => 10,
			'expired' => 5,
		];

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->once())
			->method('getRevocationStatistics')
			->willThrowException(new \RuntimeException('Database error'));
		$this->crlService->expects($this->once())
			->method('getRevokedCertificates')
			->willReturn([]);

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Error retrieving revocation statistics', $output);
		$this->assertStringContainsString('Database error', $output);
	}

	public function testHandlesGetRevokedCertificatesException(): void {
		$stats = [
			'issued' => 50,
			'revoked' => 10,
			'expired' => 5,
		];

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->once())
			->method('getRevocationStatistics')
			->willReturn([]);
		$this->crlService->expects($this->once())
			->method('getRevokedCertificates')
			->willThrowException(new \RuntimeException('Database error'));

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Error retrieving recent revocations', $output);
		$this->assertStringContainsString('Database error', $output);
	}

	#[DataProvider('providerRevocationStatisticsFormats')]
	public function testMultipleRevocationReasonsDisplay(array $revocationStats): void {
		$stats = [
			'issued' => 100,
			'revoked' => 20,
			'expired' => 5,
		];

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->once())
			->method('getRevocationStatistics')
			->willReturn($revocationStats);
		$this->crlService->expects($this->once())
			->method('getRevokedCertificates')
			->willReturn([]);

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Revocation Statistics', $output);
	}

	public static function providerRevocationStatisticsFormats(): array {
		return [
			'single-reason' => [
				[
					['description' => 'Key Compromise', 'count' => 10],
				],
			],
			'multiple-reasons' => [
				[
					['description' => 'Key Compromise', 'count' => 8],
					['description' => 'Superseded', 'count' => 7],
					['description' => 'Cessation of Operation', 'count' => 5],
				],
			],
			'unknown-reason' => [
				[
					['description' => 'Unknown', 'count' => 20],
				],
			],
		];
	}

	private function runCommand(array $input): array {
		$output = new BufferedOutput();
		$status = $this->command->run(new ArrayInput($input), $output);

		return [$status, $output->fetch()];
	}
}
