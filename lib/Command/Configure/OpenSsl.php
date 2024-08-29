<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
				name: 'cn',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Comon name'
			)
			->addOption(
				name: 'ou',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Organization unit'
			)
			->addOption(
				name: 'o',
				shortcut: 'o',
				mode: InputOption::VALUE_REQUIRED,
				description: 'Organization'
			)
			->addOption(
				name: 'c',
				shortcut: 'c',
				mode: InputOption::VALUE_REQUIRED,
				description: 'Country name'
			)
			->addOption(
				name: 'st',
				shortcut: 's',
				mode: InputOption::VALUE_REQUIRED,
				description: 'State'
			)
			->addOption(
				name: 'l',
				shortcut: 'l',
				mode: InputOption::VALUE_REQUIRED,
				description: 'Locality'
			)
			->addOption(
				name: 'config-path',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Config path'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$names = [];
		if (!$commonName = $input->getOption('cn')) {
			throw new InvalidArgumentException('Invalid Comon Name');
		}
		if ($input->getOption('ou')) {
			$names['OU'] = ['value' => $input->getOption('ou')];
		}
		if ($input->getOption('o')) {
			$names['O'] = ['value' => $input->getOption('o')];
		}
		if ($input->getOption('c')) {
			$names['C'] = ['value' => $input->getOption('c')];
		}
		if ($input->getOption('l')) {
			$names['L'] = ['value' => $input->getOption('l')];
		}
		if ($input->getOption('st')) {
			$names['ST'] = ['value' => $input->getOption('st')];
		}
		$this->installService->generate(
			(string)$commonName,
			$names,
			[
				'engine' => 'openssl'
			]
		);
		return 0;
	}
}
