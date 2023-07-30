<?php

declare(strict_types=1);

namespace OCA\Libresign\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Uninstall extends Base {
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
			->addOption('pdftk',
				null,
				InputOption::VALUE_NONE,
				'PDFtk'
			)
			->addOption('cfssl',
				null,
				InputOption::VALUE_NONE,
				'CFSSL'
			)
			->addOption('java',
				null,
				InputOption::VALUE_NONE,
				'Java'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$ok = false;

		try {
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
		return 0;
	}
}
