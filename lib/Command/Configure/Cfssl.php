<?php

declare(strict_types=1);

namespace OCA\Libresign\Command\Configure;

use InvalidArgumentException;
use OC\SystemConfig;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Command\Base;
use OCA\Libresign\Service\AdminSignatureService;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ITempManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Cfssl extends Base {
	/** @var AdminSignatureService */
	private $adminSignatureService;
	public function __construct(
		ITempManager $tempManager,
		IClientService $clientService,
		IConfig $config,
		SystemConfig $systemConfig,
		IRootFolder $rootFolder,
		AdminSignatureService $adminSignatureService
	) {
		parent::__construct(
			$tempManager,
			$clientService,
			$config,
			$systemConfig,
			$rootFolder
		);
		$this->adminSignatureService = $adminSignatureService;
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
		if ($binary = $this->config->getAppValue(Application::APP_ID, 'cfssl_bin')) {
			if (PHP_OS_FAMILY === 'Windows') {
				throw new InvalidArgumentException('Incompatible with Windows');
			}
			if ($input->getOption('config-path')) {
				throw new InvalidArgumentException('Config path is not necessary');
			}
			if ($input->getOption('cfssl-uri')) {
				throw new InvalidArgumentException('CFSSL URI is not necessary');
			}
			// create if not exist
			$this->getFolder('cfssl_config');
			$configPath = $this->getFullPath() . DIRECTORY_SEPARATOR . 'cfssl_config' . DIRECTORY_SEPARATOR;
			$cfsslUri = 'http://127.0.0.1:8888/api/v1/cfssl/';
		} else {
			$output->writeln('CFSSL binary not found!');
			if (!$configPath = $input->getOption('config-path')) {
				throw new InvalidArgumentException('Invalid config path');
			}
			if (!$cfsslUri = $input->getOption('cfssl-uri')) {
				throw new InvalidArgumentException('Invalid CFSSL API URI');
			}
		}
		$this->adminSignatureService->generate(
			$commonName,
			$country,
			$organization,
			$organizationUnit,
			$cfsslUri,
			$configPath,
			$binary
		);
		return 0;
	}
}
