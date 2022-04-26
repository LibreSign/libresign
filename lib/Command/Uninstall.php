<?php

declare(strict_types=1);

namespace OCA\Libresign\Command;

use OC\SystemConfig;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ITempManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Uninstall extends Base {
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
			->setName('libresign:uninstall')
			->setDescription('Uninstall files')
			->addOption('all',
				null,
				InputOption::VALUE_NONE,
				'All binaries'
			)
			->addOption('jsignpdf',
				null,
				InputOption::VALUE_NONE,
				'JSignPdf'
			)
			->addOption('cfssl',
				null,
				InputOption::VALUE_NONE,
				'CFSSL'
			)
			->addOption('cli',
				null,
				InputOption::VALUE_NONE,
				'LibreSign CLI'
			)
			->addOption('java',
				null,
				InputOption::VALUE_NONE,
				'Java'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$all = $input->getOption('all');
		$ok = false;
		if ($input->getOption('java') || $all) {
			$this->uninstallJava();
			$ok = true;
		}
		if ($input->getOption('jsignpdf') || $all) {
			$this->uninstallJSignPdf();
			$ok = true;
		}
		if ($input->getOption('cfssl') || $all) {
			$this->uninstallCfssl();
			$ok = true;
		}
		if ($input->getOption('cli') || $all) {
			$this->uninstallCli($output);
			$ok = true;
		}
		if (!$ok) {
			$output->writeln('<error>Please inform what you want to install</error>');
			$output->writeln('<error>--all to all</error>');
			$output->writeln('<error>--help to check the available options</error>');
		}
		return 0;
	}
}
