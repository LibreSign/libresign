<?php

declare(strict_types=1);

namespace OCA\Libresign\Command;

use OCA\Libresign\Service\InstallService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Install extends Base {
	public function __construct(
		InstallService $installService
	) {
		parent::__construct(
			$installService
		);
	}

	protected function configure(): void {
		$this
			->setName('libresign:install')
			->setDescription('Install files')
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
		$this->installService->setOutput($output);
		if ($input->getOption('java') || $all) {
			$this->installService->installJava();
			$ok = true;
		}
		if ($input->getOption('jsignpdf') || $all) {
			$this->installService->installJSignPdf();
			$ok = true;
		}
		if ($input->getOption('cfssl') || $all) {
			$this->installService->installCfssl();
			$ok = true;
		}
		if ($input->getOption('cli') || $all) {
			$this->installService->installCli();
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
