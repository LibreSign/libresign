<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
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

	protected function execute(InputInterface $input): int {
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
