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

class Cfssl extends Base {
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
			)
			->addOption(
				'cfssl-uri',
				null,
				InputOption::VALUE_REQUIRED,
				'CFSSL URI'
			);
	}

	protected function execute(InputInterface $input): int {
		if (!$this->installService->isCfsslBinInstalled()) {
			throw new InvalidArgumentException('CFSSL binary not found! run libresign:istall --cfssl first.');
		}
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

		if (PHP_OS_FAMILY === 'Windows') {
			throw new InvalidArgumentException('Incompatible with Windows');
		}
		if ($cfsslUri = $input->getOption('cfssl-uri')) {
			if (!filter_var($cfsslUri, FILTER_VALIDATE_URL)) {
				throw new InvalidArgumentException('Invalid CFSSL API URI');
			}
			if ($input->getOption('config-path')) {
				throw new InvalidArgumentException('Config path is not necessary');
			}
		}
		$configPath = $input->getOption('config-path');

		$this->installService->generate(
			$commonName,
			$names,
			[
				'engine' => 'cfssl',
				'configPath' => $configPath,
				'cfsslUri' => $cfsslUri,
			]
		);
		return 0;
	}
}
