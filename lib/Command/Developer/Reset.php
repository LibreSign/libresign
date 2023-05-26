<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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
 *
 */

namespace OCA\Libresign\Command\Developer;

use OC\Core\Command\Base;
use OCA\Libresign\AppInfo\Application;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Reset extends Base {
	public function __construct(
		private IConfig $config,
		private IDBConnection $db,
		private LoggerInterface $logger
	) {
		parent::__construct();
	}

	public function isEnabled(): bool {
		return $this->config->getSystemValue('debug', false) === true;
	}

	protected function configure(): void {
		$this
			->setName('libresign:developer:reset')
			->setDescription('Clean all LibreSign data')
			->addOption('all',
				null,
				InputOption::VALUE_NONE,
				'Reset all'
			)
			->addOption('notifications',
				null,
				InputOption::VALUE_NONE,
				'Reset notifications'
			)
			->addOption('identify',
				null,
				InputOption::VALUE_NONE,
				'Reset identify'
			)
			->addOption('fileuser',
				null,
				InputOption::VALUE_NONE,
				'Reset file user'
			)
			->addOption('file',
				null,
				InputOption::VALUE_NONE,
				'Reset file'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$ok = false;

		try {
			$all = $input->getOption('all');
			if ($input->getOption('notifications') || $all) {
				$this->resetNotifications();
				$ok = true;
			}
			if ($input->getOption('identify') || $all) {
				$this->resetIdentifyMethods();
				$ok = true;
			}
			if ($input->getOption('fileuser') || $all) {
				$this->resetFileUser();
				$ok = true;
			}
			if ($input->getOption('file') || $all) {
				$this->resetFile();
				$ok = true;
			}
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			throw $e;
		}

		if (!$ok) {
			$output->writeln('<error>Please inform what you want to reset</error>');
			$output->writeln('<error>--all to all</error>');
			$output->writeln('<error>--help to check the available options</error>');
			return 1;
		}
		return 0;
	}

	private function resetNotifications(): void {
		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('notifications')
				->where($delete->expr()->eq('app', $delete->createNamedParameter(Application::APP_ID)))
				->executeStatement();
		} catch (\Throwable $e) {
		}
	}

	private function resetIdentifyMethods(): void {
		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('libresign_identify_method')
				->executeStatement();
		} catch (\Throwable $e) {
		}
	}

	private function resetFileUser(): void {
		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('libresign_file_user')
				->executeStatement();
		} catch (\Throwable $e) {
		}
	}

	private function resetFile(): void {
		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('libresign_file')
				->executeStatement();
		} catch (\Throwable $e) {
		}
	}
}
