<?php

declare(strict_types=1);

namespace OCA\Libresign\Command\Configure;

use InvalidArgumentException;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Command\Base;
use OCA\Libresign\Service\InstallService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Cfssl extends Base {
	public function __construct(
		InstallService $installService
	) {
		parent::__construct(
			$installService
		);
	}

	protected function configure(): void {
		$this
			->setName('libresign:configure:cfssl')
			->setDescription('Configure Cfssl')
			->addOption(
				'cn',
				null,
				InputOption::VALUE_REQUIRED,
				'Comon name'
			)
			->addOption(
				'ou',
				null,
				InputOption::VALUE_REQUIRED,
				'Organization unit'
			)
			->addOption(
				'o',
				'o',
				InputOption::VALUE_REQUIRED,
				'Organization'
			)
			->addOption(
				'c',
				'c',
				InputOption::VALUE_REQUIRED,
				'Country name'
			)
			->addOption(
				'config-path',
				null,
				InputOption::VALUE_REQUIRED,
				'Config path'
			)
			->addOption(
				'cfssl-uri',
				null,
				InputOption::VALUE_REQUIRED,
				'CFSSL URI'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if (!$commonName = $input->getOption('cn')) {
			throw new InvalidArgumentException('Invalid Comon Name');
		}
		if (!$organizationUnit = $input->getOption('ou')) {
			throw new InvalidArgumentException('Invalid Organization Unit');
		}
		if (!$organization = $input->getOption('o')) {
			throw new InvalidArgumentException('Invalid Organization');
		}
		if (!$country = $input->getOption('c')) {
			throw new InvalidArgumentException('Invalid Country');
		}
		if ($binary = $this->installService->config->getAppValue(Application::APP_ID, 'cfssl_bin')) {
			if (PHP_OS_FAMILY === 'Windows') {
				throw new InvalidArgumentException('Incompatible with Windows');
			}
			if ($input->getOption('config-path')) {
				throw new InvalidArgumentException('Config path is not necessary');
			}
			if ($input->getOption('cfssl-uri')) {
				throw new InvalidArgumentException('CFSSL URI is not necessary');
			}
			$configPath = $this->installService->getConfigPath();
			$cfsslUri = null;
		} else {
			$output->writeln('<info>CFSSL binary not found! run libresign:istall --cfssl first.</info>');
			if (!$configPath = $input->getOption('config-path')) {
				throw new InvalidArgumentException('Invalid config path');
			}
			if (!$cfsslUri = $input->getOption('cfssl-uri')) {
				throw new InvalidArgumentException('Invalid CFSSL API URI');
			}
		}
		$this->installService->generate(
			$commonName,
			$country,
			$organization,
			$organizationUnit,
			$configPath,
			$cfsslUri,
			$binary
		);
		return 0;
	}
}
