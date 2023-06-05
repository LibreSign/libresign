<?php

declare(strict_types=1);

namespace OCA\Libresign\Command\Configure;

use InvalidArgumentException;
use OCA\Libresign\Command\Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OpenSsl extends Base {
	protected function configure(): void {
		$this
			->setName('libresign:configure:openssl')
			->setDescription('Configure OpenSSL')
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
				'st',
				's',
				InputOption::VALUE_REQUIRED,
				'State'
			)
			->addOption(
				'l',
				'l',
				InputOption::VALUE_REQUIRED,
				'Locality'
			)
			->addOption(
				'config-path',
				null,
				InputOption::VALUE_REQUIRED,
				'Config path'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$names = [];
		if (!$commonName = $input->getOption('cn')) {
			throw new InvalidArgumentException('Invalid Comon Name');
		}
		if ($input->getOption('ou')) {
			$names[] = ['id' => 'OU', 'value' => $input->getOption('ou')];
		}
		if ($input->getOption('o')) {
			$names[] = ['id' => 'O', 'value' => $input->getOption('o')];
		}
		if ($input->getOption('c')) {
			$names[] = ['id' => 'C', 'value' => $input->getOption('c')];
		}
		if ($input->getOption('l')) {
			$names[] = ['id' => 'L', 'value' => $input->getOption('l')];
		}
		if ($input->getOption('st')) {
			$names[] = ['id' => 'ST', 'value' => $input->getOption('st')];
		}
		$this->installService->generate(
			$commonName,
			$names,
			[
				'engine' => 'openssl'
			]
		);
		return 0;
	}
}
