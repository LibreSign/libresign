<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\Crl;

use OCA\Libresign\Service\CrlService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Stats extends Command {
	public function __construct(
		private CrlService $crlService,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('libresign:crl:stats')
			->setDescription('Display Certificate Revocation List statistics');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io = new SymfonyStyle($input, $output);

		$io->title('LibreSign CRL Statistics');

		$stats = $this->crlService->getStatistics();
		$revocationStats = $this->crlService->getRevocationStatistics();

		$totalCertificates = array_sum($stats);
		$validCertificates = $stats['issued'] ?? 0;
		$revokedCertificates = $stats['revoked'] ?? 0;
		$expiredCertificates = $stats['expired'] ?? 0;

		$io->section('Database Statistics');
		$io->table(
			['Metric', 'Count'],
			[
				['Total Certificates', $totalCertificates],
				['Valid Certificates', $validCertificates],
				['Revoked Certificates', $revokedCertificates],
				['Expired Certificates', $expiredCertificates],
			]
		);

		if (!empty($revocationStats)) {
			$io->section('Revocation Statistics');
			$revocationTable = [];
			foreach ($revocationStats as $stat) {
				$revocationTable[] = [
					$stat['reason_description'] ?? 'Unknown',
					$stat['count']
				];
			}
			$io->table(['Revocation Reason', 'Count'], $revocationTable);
		}

		$recentRevoked = $this->crlService->getRevokedCertificates();
		if (!empty($recentRevoked)) {
			$io->section('Recent Revocations (Last 10)');
			$recentTable = [];
			$count = 0;
			foreach (array_reverse($recentRevoked) as $cert) {
				if ($count >= 10) {
					break;
				}
				$recentTable[] = [
					$cert['serial_number'],
					$cert['reason_description'] ?? 'N/A',
					$cert['revoked_at'] ?? 'N/A',
					$cert['revoked_by'] ?? 'N/A'
				];
				$count++;
			}
			$io->table(['Serial Number', 'Reason', 'Revoked At', 'Revoked By'], $recentTable);
		}

		return Command::SUCCESS;
	}
}
