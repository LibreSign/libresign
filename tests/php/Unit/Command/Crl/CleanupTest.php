<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Command\Crl;

use DateTime;
use OCA\Libresign\Command\Crl\Cleanup;
use OCA\Libresign\Service\Crl\CrlService;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class CleanupTest extends TestCase {
	private CrlService&MockObject $crlService;
	private Cleanup $command;

	public function setUp(): void {
		parent::setUp();
		$this->crlService = $this->createMock(CrlService::class);
		$this->command = new Cleanup($this->crlService);
	}

	#[DataProvider('providerInvalidPeriodFormats')]
	public function testRejectsInvalidPeriodFormat(string $period, string $expectedMessage): void {
		$this->crlService->expects($this->never())
			->method('getStatistics');
		$this->crlService->expects($this->never())
			->method('cleanupExpiredCertificates');

		[$status, $output] = $this->runCommand([
			'--period' => $period,
		]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString($expectedMessage, $output);
	}

	public static function providerInvalidPeriodFormats(): array {
		return [
			'invalid-words' => ['invalid period', 'Invalid period format'],
			'negative-period' => ['-1 year', 'Invalid period format'],
			'empty-period' => ['', 'Invalid period format'],
			'numeric-only' => ['365', 'Invalid period format'],
		];
	}

	public function testDefaultPeriodIsOneYear(): void {
		$stats = $this->getDefaultStatistics();

		$this->crlService->expects($this->exactly(2))
			->method('getStatistics')
			->willReturnOnConsecutiveCalls($stats, $stats);
		$this->crlService->expects($this->once())
			->method('cleanupExpiredCertificates')
			->with($this->callback(function (DateTime $date) {
				$expectedDate = new DateTime();
				$expectedDate->modify('-1 year');
				// Allow 1 second difference for test execution time
				return abs($date->getTimestamp() - $expectedDate->getTimestamp()) <= 1;
			}))
			->willReturn(0);

		[$status, $output] = $this->runCommand([]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Period: 1 year', $output);
	}

	public function testCustomPeriodIsProcessed(): void {
		$stats = $this->getDefaultStatistics();

		$this->crlService->expects($this->exactly(2))
			->method('getStatistics')
			->willReturnOnConsecutiveCalls($stats, $stats);
		$this->crlService->expects($this->once())
			->method('cleanupExpiredCertificates')
			->with($this->callback(function (DateTime $date) {
				$expectedDate = new DateTime();
				$expectedDate->modify('-6 months');
				return abs($date->getTimestamp() - $expectedDate->getTimestamp()) <= 1;
			}))
			->willReturn(0);

		[$status, $output] = $this->runCommand([
			'--period' => '6 months',
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Period: 6 months', $output);
	}

	public function testDryRunDoesNotPerformCleanup(): void {
		$stats = [
			'issued' => 50,
			'revoked' => 10,
			'expired' => 15,
		];

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->never())
			->method('cleanupExpiredCertificates');

		[$status, $output] = $this->runCommand([
			'--period' => '1 year',
			'--dry-run' => true,
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('DRY-RUN mode', $output);
		$this->assertStringContainsString('Dry Run Results', $output);
		$this->assertStringContainsString('Would clean up expired certificates', $output);
		$this->assertStringContainsString('Total Certificates', $output);
		$this->assertStringContainsString('75', $output); // 50 + 10 + 15
		$this->assertStringContainsString('Valid Certificates', $output);
		$this->assertStringContainsString('50', $output);
		$this->assertStringContainsString('Revoked Certificates', $output);
		$this->assertStringContainsString('10', $output);
		$this->assertStringContainsString('Expired Certificates', $output);
		$this->assertStringContainsString('15', $output);
		$this->assertStringContainsString('no changes will be made', $output);
	}

	public function testSuccessfulCleanupWithCertificatesDeleted(): void {
		$beforeStats = [
			'issued' => 50,
			'revoked' => 10,
			'expired' => 15,
		];
		$afterStats = [
			'issued' => 50,
			'revoked' => 10,
			'expired' => 5,
		];

		$this->crlService->expects($this->exactly(2))
			->method('getStatistics')
			->willReturnOnConsecutiveCalls($beforeStats, $afterStats);
		$this->crlService->expects($this->once())
			->method('cleanupExpiredCertificates')
			->willReturn(10);

		[$status, $output] = $this->runCommand([
			'--period' => '1 year',
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Successfully cleaned up 10 expired certificate(s)', $output);
		$this->assertStringContainsString('Updated Statistics', $output);
		$this->assertStringContainsString('Before', $output);
		$this->assertStringContainsString('After', $output);
		$this->assertStringContainsString('Change', $output);
		// Check the before values
		$this->assertStringContainsString('75', $output); // Total before: 50 + 10 + 15
		// Check the after values
		$this->assertStringContainsString('65', $output); // Total after: 50 + 10 + 5
		// Check change
		$this->assertStringContainsString('-10', $output);
	}

	public function testNoCertificatesFoundForCleanup(): void {
		$stats = [
			'issued' => 50,
			'revoked' => 10,
			'expired' => 0,
		];

		$this->crlService->expects($this->exactly(2))
			->method('getStatistics')
			->willReturnOnConsecutiveCalls($stats, $stats);
		$this->crlService->expects($this->once())
			->method('cleanupExpiredCertificates')
			->willReturn(0);

		[$status, $output] = $this->runCommand([
			'--period' => '1 year',
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('No expired certificates found for cleanup', $output);
		$this->assertStringContainsString('Updated Statistics', $output);
	}

	#[DataProvider('providerVariousPeriods')]
	public function testVariousValidPeriods(string $period): void {
		$stats = $this->getDefaultStatistics();

		$this->crlService->expects($this->exactly(2))
			->method('getStatistics')
			->willReturnOnConsecutiveCalls($stats, $stats);
		$this->crlService->expects($this->once())
			->method('cleanupExpiredCertificates')
			->willReturn(0);

		[$status, $output] = $this->runCommand([
			'--period' => $period,
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString("Period: {$period}", $output);
	}

	public static function providerVariousPeriods(): array {
		return [
			'30-days' => ['30 days'],
			'3-months' => ['3 months'],
			'6-months' => ['6 months'],
			'1-year' => ['1 year'],
			'2-years' => ['2 years'],
			'1-week' => ['1 week'],
		];
	}

	public function testHandlesCleanupException(): void {
		$stats = [
			'issued' => 50,
			'revoked' => 10,
			'expired' => 15,
		];

		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->once())
			->method('cleanupExpiredCertificates')
			->willThrowException(new \RuntimeException('Database connection lost'));

		[$status, $output] = $this->runCommand([
			'--period' => '1 year',
		]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString('Error during cleanup', $output);
		$this->assertStringContainsString('Database connection lost', $output);
	}

	public function testHandlesGetStatisticsException(): void {
		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willThrowException(new \RuntimeException('Database error'));
		$this->crlService->expects($this->never())
			->method('cleanupExpiredCertificates');

		[$status, $output] = $this->runCommand([
			'--period' => '1 year',
		]);

		$this->assertSame(Command::FAILURE, $status);
		$this->assertStringContainsString('Error during cleanup', $output);
		$this->assertStringContainsString('Database error', $output);
	}

	public function testDisplaysCurrentStatistics(): void {
		$stats = [
			'issued' => 100,
			'revoked' => 25,
			'expired' => 30,
		];

		$this->crlService->expects($this->exactly(2))
			->method('getStatistics')
			->willReturnOnConsecutiveCalls($stats, $stats);
		$this->crlService->expects($this->once())
			->method('cleanupExpiredCertificates')
			->willReturn(0);

		[$status, $output] = $this->runCommand([
			'--period' => '1 year',
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Current Statistics', $output);
		$this->assertStringContainsString('Total Certificates', $output);
		$this->assertStringContainsString('155', $output); // 100 + 25 + 30
		$this->assertStringContainsString('Valid Certificates', $output);
		$this->assertStringContainsString('100', $output);
		$this->assertStringContainsString('Revoked Certificates', $output);
		$this->assertStringContainsString('25', $output);
		$this->assertStringContainsString('Expired Certificates', $output);
		$this->assertStringContainsString('30', $output);
	}

	public function testShowsCleanupDateInOutput(): void {
		$stats = $this->getDefaultStatistics();

		$this->crlService->expects($this->exactly(2))
			->method('getStatistics')
			->willReturnOnConsecutiveCalls($stats, $stats);
		$this->crlService->expects($this->once())
			->method('cleanupExpiredCertificates')
			->willReturn(0);

		[$status, $output] = $this->runCommand([
			'--period' => '1 year',
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Cleanup Configuration', $output);
		$this->assertStringContainsString('Cleanup date:', $output);
		// Check date format YYYY-MM-DD HH:MM:SS
		$this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $output);
	}

	#[DataProvider('providerCompleteDryRunScenarios')]
	public function testCompleteDryRunScenarios(array $stats, string $expectedPattern): void {
		$this->crlService->expects($this->once())
			->method('getStatistics')
			->willReturn($stats);
		$this->crlService->expects($this->never())
			->method('cleanupExpiredCertificates');

		[$status, $output] = $this->runCommand([
			'--period' => '1 year',
			'--dry-run' => true,
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('DRY-RUN mode', $output);
		$this->assertMatchesRegularExpression($expectedPattern, $output);
	}

	public static function providerCompleteDryRunScenarios(): array {
		return [
			'no-certificates' => [
				['issued' => 0, 'revoked' => 0, 'expired' => 0],
				'/Total Certificates.*0/',
			],
			'only-valid' => [
				['issued' => 100, 'revoked' => 0, 'expired' => 0],
				'/Valid Certificates.*100/',
			],
			'only-revoked' => [
				['issued' => 0, 'revoked' => 50, 'expired' => 0],
				'/Revoked Certificates.*50/',
			],
			'only-expired' => [
				['issued' => 0, 'revoked' => 0, 'expired' => 75],
				'/Expired Certificates.*75/',
			],
			'mixed-certificates' => [
				['issued' => 50, 'revoked' => 25, 'expired' => 10],
				'/Total Certificates.*85/',
			],
		];
	}

	public function testHandlesPartialStatistics(): void {
		$partialStats = [
			'issued' => 50,
			// missing 'revoked' and 'expired'
		];
		$afterStats = [
			'issued' => 50,
			'revoked' => 0,
			'expired' => 0,
		];

		$this->crlService->expects($this->exactly(2))
			->method('getStatistics')
			->willReturnOnConsecutiveCalls($partialStats, $afterStats);
		$this->crlService->expects($this->once())
			->method('cleanupExpiredCertificates')
			->willReturn(0);

		[$status, $output] = $this->runCommand([
			'--period' => '1 year',
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Total Certificates', $output);
		$this->assertStringContainsString('50', $output);
	}

	public function testLargeNumberOfCleanedCertificates(): void {
		$beforeStats = [
			'issued' => 1000,
			'revoked' => 500,
			'expired' => 2000,
		];
		$afterStats = [
			'issued' => 1000,
			'revoked' => 500,
			'expired' => 100,
		];

		$this->crlService->expects($this->exactly(2))
			->method('getStatistics')
			->willReturnOnConsecutiveCalls($beforeStats, $afterStats);
		$this->crlService->expects($this->once())
			->method('cleanupExpiredCertificates')
			->willReturn(1900);

		[$status, $output] = $this->runCommand([
			'--period' => '1 year',
		]);

		$this->assertSame(Command::SUCCESS, $status);
		$this->assertStringContainsString('Successfully cleaned up 1900 expired certificate(s)', $output);
		$this->assertStringContainsString('-1900', $output); // Change in total
	}

	private function runCommand(array $input): array {
		$output = new BufferedOutput();
		$status = $this->command->run(new ArrayInput($input), $output);

		return [$status, $output->fetch()];
	}

	private function getDefaultStatistics(): array {
		return [
			'issued' => 10,
			'revoked' => 5,
			'expired' => 3,
		];
	}
}
