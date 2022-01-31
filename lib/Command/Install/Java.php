<?php

declare(strict_types=1);

namespace OCA\Libresign\Command\Install;

use OC\SystemConfig;
use OCA\Libresign\Command\Base;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ITempManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Java extends Base {
	public function __construct(
		ITempManager $tempManager,
		IClientService $clientService,
		IConfig $config,
		SystemConfig $systemConfig,
		IRootFolder $rootFolder
	) {
		parent::__construct(
			$tempManager,
			$clientService,
			$config,
			$systemConfig,
			$rootFolder
		);
	}

	protected function configure(): void {
		$this
			->setName('libresign:install:java')
			->setDescription('Download and configure Java')
			->addOption('uninstall',
				null,
				InputOption::VALUE_NONE,
				'Uninstall standalone Java binaries'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ($input->getOption('uninstall')) {
			$this->uninstallJava();
		} else {
			$this->installJava();
		}
		return static::SUCCESS;
	}
}
