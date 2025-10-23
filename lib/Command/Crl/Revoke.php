<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\Crl;

use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Service\CrlService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Revoke extends Command {
	public function __construct(
		private CrlService $crlService,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$validReasons = [];
		foreach (CRLReason::cases() as $reason) {
			$validReasons[] = $reason->value . '=' . $reason->getDescription();
		}
		$reasonDescription = 'Revocation reason code (' . implode(', ', $validReasons) . ')';

		$this
			->setName('libresign:crl:revoke')
			->setDescription('Revoke a certificate by serial number')
			->addArgument(
				'serial-number',
				InputArgument::REQUIRED,
				'Serial number of the certificate to revoke'
			)
			->addOption(
				'reason',
				'r',
				InputOption::VALUE_REQUIRED,
				$reasonDescription,
				'0'
			)
			->addOption(
				'reason-text',
				't',
				InputOption::VALUE_REQUIRED,
				'Optional reason description text'
			)
			->addOption(
				'dry-run',
				null,
				InputOption::VALUE_NONE,
				'Show what would be done without making changes'
			);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io = new SymfonyStyle($input, $output);
		$isDryRun = $input->getOption('dry-run');
		$serialNumber = $input->getArgument('serial-number');
		$reasonCode = (int)$input->getOption('reason');
		$reasonText = $input->getOption('reason-text');

		if ($isDryRun) {
			$io->note('Running in DRY-RUN mode - no changes will be made');
		}

		$io->title('LibreSign CRL Certificate Revocation');

		if (!is_numeric($serialNumber) || (int)$serialNumber <= 0) {
			$io->error("Invalid serial number: {$serialNumber}. Must be a positive integer.");
			return Command::FAILURE;
		}

		if (!CRLReason::isValid($reasonCode)) {
			$validCodes = array_map(fn ($case) => $case->value, CRLReason::cases());
			$io->error("Invalid reason code: {$reasonCode}. Valid codes are: " . implode(', ', $validCodes));
			return Command::FAILURE;
		}

		$reason = CRLReason::from($reasonCode);
		$reasonDescription = $reason->getDescription();

		$io->section('Revocation Details');
		$io->table(
			['Field', 'Value'],
			[
				['Serial Number', $serialNumber],
				['Reason Code', $reasonCode],
				['Reason', $reasonDescription],
				['Description', $reasonText ?? 'N/A'],
			]
		);

		try {
			$status = $this->crlService->getCertificateStatus((int)$serialNumber);

			$io->section('Current Certificate Status');
			$io->text("Status: {$status['status']}");

			if ($status['status'] === 'revoked') {
				$io->warning("Certificate {$serialNumber} is already revoked.");
				if (isset($status['reason_code'])) {
					$currentReason = CRLReason::tryFrom($status['reason_code']);
					$currentReasonDescription = $currentReason?->getDescription() ?? 'unknown';
					$io->text("Current reason: {$currentReasonDescription} (code: {$status['reason_code']})");
				}
				if (isset($status['revoked_at'])) {
					$io->text("Revoked at: {$status['revoked_at']}");
				}
				return Command::SUCCESS;
			}

			if ($status['status'] === 'unknown') {
				$io->error("Certificate {$serialNumber} not found in the database.");
				return Command::FAILURE;
			}

		} catch (\Exception $e) {
			$io->error('Error checking certificate status: ' . $e->getMessage());
			return Command::FAILURE;
		}

		if ($isDryRun) {
			$io->section('Dry Run Results');
			$io->text("Would revoke certificate with serial number: {$serialNumber}");
			$io->text("Reason: {$reasonDescription} (code: {$reasonCode})");
			if ($reasonText) {
				$io->text("Description: {$reasonText}");
			}
			$io->warning('Use --dry-run=false or remove --dry-run to perform actual revocation');
			return Command::SUCCESS;
		}

		$io->section('Performing Revocation');
		try {
			$success = $this->crlService->revokeCertificate(
				(int)$serialNumber,
				$reasonCode,
				$reasonText,
				'cli-admin'
			);

			if ($success) {
				$io->success("Certificate {$serialNumber} has been revoked successfully.");
				$io->text("Reason: {$reasonDescription} (code: {$reasonCode})");
				if ($reasonText) {
					$io->text("Description: {$reasonText}");
				}

				$io->note('The CRL will be regenerated on the next request to include this revocation.');
			} else {
				$io->error("Failed to revoke certificate {$serialNumber}");
				return Command::FAILURE;
			}
		} catch (\Exception $e) {
			$io->error('Error revoking certificate: ' . $e->getMessage());
			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}
}
