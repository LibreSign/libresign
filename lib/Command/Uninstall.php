<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Uninstall extends Base {
	protected function configure(): void {
		$this
			->setName('libresign:uninstall')
			->setDescription('Uninstall files')
			->addOption(
				name: 'all',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'All binaries'
			)
			->addOption(
				name: 'jsignpdf',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'JSignPdf'
			)
			->addOption(
				name: 'pdftk',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'PDFtk'
			)
			->addOption(
				name: 'cfssl',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'CFSSL'
			)
			->addOption(
				name: 'java',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Java'
			)
			->addOption(
				name: 'architecture',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'x86_64 or aarch64'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$ok = false;

		try {
			$architecture = (string)$input->getOption('architecture');
			if (in_array($architecture, ['x86_64', 'aarch64'])) {
				$this->installService->setArchitecture($architecture);
			}
			$all = $input->getOption('all');
			if ($input->getOption('java') || $all) {
				$this->installService->uninstallJava();
				$ok = true;
			}
			if ($input->getOption('jsignpdf') || $all) {
				$this->installService->uninstallJSignPdf();
				$ok = true;
			}
			if ($input->getOption('pdftk') || $all) {
				$this->installService->uninstallPdftk();
				$ok = true;
			}
			if ($input->getOption('cfssl') || $all) {
				$this->installService->uninstallCfssl();
				$ok = true;
			}
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			throw $e;
		}

		if (!$ok) {
			$output->writeln('<error>Please inform what you want to install</error>');
			$output->writeln('<error>--all to all</error>');
			$output->writeln('<error>--help to check the available options</error>');
			return 1;
		}
		$output->writeln('Finished with success.');
		return 0;
	}
}
