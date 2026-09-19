<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command;

use OCA\Libresign\Service\Install\InstallTarget;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Uninstall extends Base {
	#[\Override]
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
				description: 'x86_64/amd64 or aarch64/arm64'
			);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$architecture = (string)$input->getOption('architecture');
			if ($architecture !== '') {
				$this->installService->setArchitecture(
					InstallTarget::normalizeArchitecture($architecture),
				);
			}

			$resources = $this->getRequestedResources($input);
			if ($resources === []) {
				$output->writeln('<error>Please inform what you want to install</error>');
				$output->writeln('<error>--all to all</error>');
				$output->writeln('<error>--help to check the available options</error>');
				return 1;
			}

			foreach ($resources as $resource) {
				$this->installService->uninstall($resource);
			}
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			throw $e;
		}

		$output->writeln('Finished with success.');
		return 0;
	}

	/**
	 * @return list<string>
	 */
	private function getRequestedResources(InputInterface $input): array {
		if ($input->getOption('all')) {
			return array_values($this->installService->getAvailableResources());
		}

		$resources = [];
		foreach ($this->installService->getAvailableResources() as $resource) {
			if ($input->getOption($resource)) {
				$resources[] = $resource;
			}
		}
		return $resources;
	}
}
