<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\IdDocs;

use OCA\Libresign\Service\IdDocsRecoveryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Administrator-initiated repair of identification documents orphaned in appdata. */
class Recover extends Command {
	public function __construct(
		private IdDocsRecoveryService $recoveryService,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this->setName('libresign:id-docs:recover')
			->setDescription('Find identification documents of external signers stored in the unauthenticated appdata folder and copy them to the owner storage')
			->setHelp(
				"Runs as a dry-run by default and only reports what would change.\n"
				. "With --execute, each affected document is copied into a new folder of the owner,\n"
				. "its content is compared with the original, and only then is the file record\n"
				. 'updated to the copy. The original stays in appdata and must be removed manually.'
			)
			->addOption('execute', null, InputOption::VALUE_NONE, 'Copy the affected documents and update their file records');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$execute = (bool)$input->getOption('execute');
		$results = $this->recoveryService->run($execute);

		if ($results !== []) {
			$table = new Table($output);
			$table->setHeaders(['ID doc', 'File', 'Node', 'Status', 'Reason', 'New node']);
			foreach ($results as $result) {
				$table->addRow([
					$result['idDocId'],
					$result['fileId'],
					$result['nodeId'] ?? '',
					$result['status'],
					$result['reason'],
					$result['newNodeId'] ?? $result['leftoverNodeId'] ?? '',
				]);
			}
			$table->render();
		}

		$totals = array_count_values(array_column($results, 'status'));
		ksort($totals);
		$summary = [];
		foreach ($totals as $status => $total) {
			$summary[] = $status . ': ' . $total;
		}
		$output->writeln('Checked ' . count($results) . ' identification documents' . ($summary === [] ? '.' : ' (' . implode(', ', $summary) . ').'));
		if (!$execute && isset($totals[IdDocsRecoveryService::STATUS_AFFECTED])) {
			$output->writeln('Dry-run: nothing was changed. Run again with --execute to recover the affected documents.');
		}

		return isset($totals[IdDocsRecoveryService::STATUS_FAILED]) ? self::FAILURE : self::SUCCESS;
	}
}
