<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\Configure;

use OC\Core\Command\Base;
use OCA\Libresign\Service\Install\ConfigureCheckService;
use OCP\IConfig;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Check extends Base {
	public function __construct(
		private ConfigureCheckService $configureCheckService,
		private IConfig $config,
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

		$result = [];
		if ($all) {
			$result = $this->configureCheckService->checkAll();
		} else {
			if ($sign) {
				$result = array_merge($result, $this->configureCheckService->checkSign());
			}
			if ($certificate) {
				$result = array_merge($result, $this->configureCheckService->checkCertificate());
			}
		}

		if (count($result)) {
			$table = new Table($output);
			$table->setColumnMaxWidth(3, 40);
			foreach ($result as $row) {
				$table->addRow([
					new TableCell($row->getStatus(), ['style' => new TableCellStyle([
						'bg' => $this->getStatusColor($row->getStatus()),
						'fg' => 'black',
						'align' => 'center',
					])]),
					$row->getResource(),
					$row->getMessage(),
					$row->getTip(),
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

	private function getStatusColor($status): string {
		return match ($status) {
			'success' => 'green',
			'error' => 'red',
			'info' => 'bright-yellow',
			default => 'red',
		};
	}
}
