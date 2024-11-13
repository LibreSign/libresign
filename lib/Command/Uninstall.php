<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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
