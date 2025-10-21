<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\Crl;

use DateTime;
use OCA\Libresign\Service\CrlService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Cleanup extends Command {
	public function __construct(
		private CrlService $crlService,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('libresign:crl:cleanup')
			->setDescription('Clean up expired certificates from the CRL database')
			->addOption(
				'period',
				'p',
				InputOption::VALUE_REQUIRED,
				'Clean up expired certificates older than specified period (e.g., "1 year", "6 months")',
				'1 year'
			)
			->addOption(
				'dry-run',
				null,
				InputOption::VALUE_NONE,
				'Show what would be cleaned without making changes'
			);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io = new SymfonyStyle($input, $output);
		$isDryRun = $input->getOption('dry-run');
		$period = $input->getOption('period');

		if ($isDryRun) {
			$io->note('Running in DRY-RUN mode - no changes will be made');
		}

		$io->title('LibreSign CRL Cleanup');

		try {
			$cleanupDate = new DateTime();
			$cleanupDate->modify("-{$period}");
		} catch (\Exception $e) {
			$io->error("Invalid period format: {$period}. Use formats like '1 year', '6 months', '30 days'");
			return Command::FAILURE;
		}

		$io->section('Cleanup Configuration');
		$io->text("Period: {$period}");
		$io->text('Cleanup date: ' . $cleanupDate->format('Y-m-d H:i:s'));

		$stats = $this->crlService->getStatistics();

		$totalCertificates = array_sum($stats);
		$validCertificates = $stats['issued'] ?? 0;
		$revokedCertificates = $stats['revoked'] ?? 0;
		$expiredCertificates = $stats['expired'] ?? 0;

		$io->section('Current Statistics');
		$io->table(
			['Metric', 'Count'],
			[
				['Total Certificates', $totalCertificates],
				['Valid Certificates', $validCertificates],
				['Revoked Certificates', $revokedCertificates],
				['Expired Certificates', $expiredCertificates],
			]
		);

		if ($isDryRun) {
			$io->section('Dry Run Results');
			$io->text('Would clean up expired certificates older than ' . $cleanupDate->format('Y-m-d H:i:s'));
			$io->warning('Use --dry-run=false or remove --dry-run to perform actual cleanup');
			return Command::SUCCESS;
		}

		$io->section('Performing Cleanup');
		try {
			$deletedCount = $this->crlService->cleanupExpiredCertificates($cleanupDate);

			if ($deletedCount > 0) {
				$io->success("Successfully cleaned up {$deletedCount} expired certificate(s)");
			} else {
				$io->info('No expired certificates found for cleanup');
			}

			$newStats = $this->crlService->getStatistics();

			$newTotalCertificates = array_sum($newStats);
			$newValidCertificates = $newStats['issued'] ?? 0;
			$newRevokedCertificates = $newStats['revoked'] ?? 0;
			$newExpiredCertificates = $newStats['expired'] ?? 0;

			$io->section('Updated Statistics');
			$io->table(
				['Metric', 'Before', 'After', 'Change'],
				[
					['Total Certificates', $totalCertificates, $newTotalCertificates, $newTotalCertificates - $totalCertificates],
					['Valid Certificates', $validCertificates, $newValidCertificates, $newValidCertificates - $validCertificates],
					['Revoked Certificates', $revokedCertificates, $newRevokedCertificates, $newRevokedCertificates - $revokedCertificates],
					['Expired Certificates', $expiredCertificates, $newExpiredCertificates, $newExpiredCertificates - $expiredCertificates],
				]
			);

		} catch (\Exception $e) {
			$io->error('Error during cleanup: ' . $e->getMessage());
			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}
}
