<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command;

use InvalidArgumentException;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Install\InstallService;
use OCA\Libresign\Service\Install\InstallTarget;
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

	#[\Override]
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
				description: 'x86_64/amd64 or aarch64/arm64'
			)
			->addOption(
				name: 'distro',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'linux or alpine-linux'
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

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->installService->setOutput($output);

		try {
			$this->configureTarget($input);

			if ($input->hasOption('use-local-cert') && $input->getOption('use-local-cert')) {
				$this->installService->willUseLocalCert();
			}

			$resources = $this->getRequestedResources($input);
			if ($resources === []) {
				$output->writeln('<error>Please inform what you want to install</error>');
				$output->writeln('<error>--all to all</error>');
				$output->writeln('<error>--help to check the available options</error>');
				return 1;
			}

			$this->installResources($resources, $input);

			if (in_array('cfssl', $resources, true)) {
				$currentEngine = $this->appConfig->getValueString(
					Application::APP_ID,
					'certificate_engine',
					'openssl',
				);
				if ($currentEngine !== 'cfssl') {
					$output->writeln('<comment>To use CFSSL, set the engine to cfssl with:</comment> config:app:set libresign certificate_engine --value=cfssl');
				}
			}
		} catch (\Exception $e) {
			$this->installService->saveErrorMessage($e->getMessage());
			$this->logger->error($e->getMessage());
			throw $e;
		}

		$output->writeln('Finished with success.');

		return 0;
	}

	private function configureTarget(InputInterface $input): void {
		$architecture = (string)$input->getOption('architecture');
		if ($architecture !== '') {
			$this->installService->setArchitecture(
				InstallTarget::normalizeArchitecture($architecture),
			);
		}

		$distro = (string)$input->getOption('distro');
		if ($distro !== '') {
			if ($input->getOption('all-distros')) {
				throw new InvalidArgumentException('--distro and --all-distros cannot be used together.');
			}
			$this->installService->setDistro($distro);
		}
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

	/**
	 * @param list<string> $resources
	 */
	private function installResources(array $resources, InputInterface $input): void {
		foreach ($resources as $resource) {
			if ($resource === 'java' && $input->getOption('all-distros')) {
				$currentDistro = $this->installService->getLinuxDistributionToDownloadJava();
				if ($currentDistro === 'linux') {
					$distros = ['alpine-linux', 'linux'];
				} else {
					$distros = ['linux', 'alpine-linux'];
				}
				foreach ($distros as $distro) {
					$this->installService->setDistro($distro);
					$this->installService->install($resource);
				}
				continue;
			}

			$this->installService->install($resource);
		}
	}
}
