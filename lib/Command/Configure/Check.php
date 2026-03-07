<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\Configure;

use OC\Core\Command\Base;
use OCA\Libresign\Service\SetupCheckResultService;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Check extends Base {

	public function __construct(
		private SetupCheckResultService $setupCheckResultService,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('libresign:configure:check')
			->setDescription('Check configure')
			->addOption(
				name: 'sign',
				shortcut: 's',
				mode: InputOption::VALUE_NONE,
				description: 'Check requirements to sign document'
			)
			->addOption(
				name: 'certificate',
				shortcut: 'c',
				mode: InputOption::VALUE_NONE,
				description: 'Check requirements to use root certificate'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$sign = $input->getOption('sign');
		$certificate = $input->getOption('certificate');
		$all = (!$sign && !$certificate);

		$allChecks = $this->setupCheckResultService->getFormattedChecks();

		$filteredRows = array_filter($allChecks, function ($check) use ($all, $sign, $certificate) {
			if ($all) {
				return true;
			}
			if ($sign && $check['category'] === 'system') {
				return true;
			}
			if ($certificate && $check['category'] === 'security') {
				return true;
			}
			return false;
		});

		if (!empty($filteredRows)) {
			$table = new Table($output);
			$table->setColumnMaxWidth(3, 40);
			foreach ($filteredRows as $row) {
				$table->addRow([
					new TableCell($row['status'], ['style' => new TableCellStyle([
						'bg' => $this->getStatusColor($row['status']),
						'fg' => 'black',
						'align' => 'center',
					])]),
					$row['resource'],
					$row['message'],
					$row['tip'],
				]);
			}
			$table
				->setHeaders([
					'Status',
					'Resource',
					'Message',
					'Tip',
				])
				->setStyle('symfony-style-guide')
				->render();
		}

		return 0;
	}

	private function getStatusColor(string $status): string {
		return match ($status) {
			'success' => 'green',
			'error' => 'red',
			'info' => 'bright-yellow',
			default => 'red',
		};
	}
}
