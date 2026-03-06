<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\Configure;

use OC\Core\Command\Base;
use OCP\SetupCheck\ISetupCheckManager;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Check extends Base {
	public function __construct(
		private ContainerInterface $container,
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

		$checkManager = $this->container->get(ISetupCheckManager::class);
		$allResults = $checkManager->runAll();

		$filteredRows = [];

		foreach ($allResults as $category => $checks) {
			foreach ($checks as $checkName => $result) {

				if (!str_starts_with($checkName, 'OCA\\Libresign\\SetupCheck\\')) {
					continue;
				}

				$includeCategory = $all
					|| ($sign && $category === 'system')
					|| ($certificate && $category === 'security');

				if (!$includeCategory) {
					continue;
				}

				$status = $this->mapSeverityToStatus($result->getSeverity());
				$shortName = substr($checkName, strrpos($checkName, '\\') + 1);
				$resource = str_replace('SetupCheck', '', $shortName);

				$filteredRows[] = [
					'status' => $status,
					'resource' => $resource,
					'message' => $result->getDescription(),
					'tip' => $result->getLinkToDoc() ?? '',
				];
			}
		}

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

	private function mapSeverityToStatus(string $severity): string {
		return match ($severity) {
			'error' => 'error',
			'warning' => 'info',
			'success' => 'success',
			default => 'error',
		};
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
