<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command;

use OCA\Libresign\Service\Install\InstallService;
use OCP\IAppConfig;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Install extends Base {
	public function __construct(
		InstallService $installService,
		LoggerInterface $logger,
		private IAppConfig $appConfig,
		private IConfig $config,
	) {
		parent::__construct($installService, $logger);
	}

	protected function configure(): void {
		$this
			->setName('libresign:install')
			->setDescription('Install files')
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
			)
			->addOption(
				name: 'all-distros',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Will download java to all available distros'
			);
		if ($this->config->getSystemValue('debug', false) === true) {
			$this->addOption(
				name: 'use-local-cert',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Use local cert'
			);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$ok = false;
		$this->installService->setOutput($output);

		try {
			$architecture = (string)$input->getOption('architecture');
			if (in_array($architecture, ['x86_64', 'aarch64'])) {
				$this->installService->setArchitecture($architecture);
			}
			if ($input->hasOption('use-local-cert') && $input->getOption('use-local-cert')) {
				$this->installService->willUseLocalCert();
			}
			$all = $input->getOption('all');
			if ($input->getOption('java') || $all) {
				if ($input->getOption('all-distros')) {
					$currentDistro = $this->installService->getLinuxDistributionToDownloadJava();
					if ($currentDistro === 'linux') {
						$distros = ['alpine-linux', 'linux'];
					} else {
						$distros = ['linux', 'alpine-linux'];
					}
					foreach ($distros as $distro) {
						$this->installService->setDistro($distro);
						$this->installService->installJava();
					}
				} else {
					$this->installService->installJava();
				}
				$ok = true;
			}
			if ($input->getOption('jsignpdf') || $all) {
				$this->installService->installJSignPdf();
				$ok = true;
			}
			if ($input->getOption('pdftk') || $all) {
				$this->installService->installPdftk();
				$ok = true;
			}
			if ($input->getOption('cfssl') || $all) {
				$currentEngine = $this->appConfig->getValueString('certificate_engine', 'openssl');
				$this->installService->installCfssl();
				if ($currentEngine !== 'cfssl') {
					$output->writeln('<comment>To use CFSSL, set the engine to cfssl with:</comment> config:app:set libresign certificate_engine --value=cfssl');
				}
				$ok = true;
			}
		} catch (\Exception $e) {
			$this->installService->saveErrorMessage($e->getMessage());
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
