<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Command\Developer;

use OC\Core\Command\Base;
use OCA\Libresign\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
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
		private LoggerInterface $logger,
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
			->addOption(
				name: 'all',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Reset all'
			)
			->addOption(
				name: 'notifications',
				shortcut: null,
				mode: InputOption::VALUE_OPTIONAL,
				description: 'Reset notifications'
			)
			->addOption(
				name: 'activity',
				shortcut: null,
				mode: InputOption::VALUE_OPTIONAL,
				description: 'Reset activity'
			)
			->addOption(
				name: 'identify',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Reset identify'
			)
			->addOption(
				name: 'signrequest',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Reset sign request'
			)
			->addOption(
				name: 'file',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Reset file'
			)
			->addOption(
				name: 'fileelement',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Reset file element'
			)
			->addOption(
				name: 'userelement',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Reset user element'
			)
			->addOption(
				name: 'config',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Reset config'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$ok = false;

		try {
			$all = $input->getOption('all');
			if ($input->getOption('notifications') || $all) {
				$this->resetNotifications((string)$input->getOption('notifications'));
				$ok = true;
			}
			if ($input->getOption('activity') || $all) {
				$this->resetActivity((string)$input->getOption('activity'));
				$ok = true;
			}
			if ($input->getOption('identify') || $all) {
				$this->resetIdentifyMethods();
				$ok = true;
			}
			if ($input->getOption('signrequest') || $all) {
				$this->resetSignRequest();
				$ok = true;
			}
			if ($input->getOption('file') || $all) {
				$this->resetFile();
				$ok = true;
			}
			if ($input->getOption('fileelement') || $all) {
				$this->resetFileElement();
				$ok = true;
			}
			if ($input->getOption('userelement') || $all) {
				$this->resetUserElement();
				$ok = true;
			}
			if ($input->getOption('config') || $all) {
				$this->resetConfig();
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

	private function resetNotifications(string $user): void {
		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('notifications')
				->where($delete->expr()->eq('app', $delete->createNamedParameter(Application::APP_ID)));
			if ($user) {
				$delete->andWhere($delete->expr()->eq('user', $delete->createNamedParameter($user)));
			}
			$delete->executeStatement();
		} catch (\Throwable) {
		}
	}

	private function resetActivity(string $user): void {
		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('activity_mq')
				->where($delete->expr()->eq('amq_appid', $delete->createNamedParameter(Application::APP_ID)));
			if ($user) {
				$delete->andWhere($delete->expr()->eq('amq_affecteduser', $delete->createNamedParameter($user)));
			}
			$delete->executeStatement();

			$delete = $this->db->getQueryBuilder();
			$delete->delete('activity')
				->where($delete->expr()->eq('app', $delete->createNamedParameter(Application::APP_ID)));
			if ($user) {
				$delete->andWhere($delete->expr()->eq('user', $delete->createNamedParameter($user)));
			}
			$delete->executeStatement();
		} catch (\Throwable) {
		}
	}

	private function resetIdentifyMethods(): void {
		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('libresign_identify_method')
				->executeStatement();
		} catch (\Throwable) {
		}
	}

	private function resetSignRequest(): void {
		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('libresign_sign_request')
				->executeStatement();
		} catch (\Throwable) {
		}
	}

	private function resetFile(): void {
		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('libresign_file')
				->executeStatement();
		} catch (\Throwable) {
		}
	}

	private function resetFileElement(): void {
		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('libresign_file_element')
				->executeStatement();
		} catch (\Throwable) {
		}
	}

	private function resetUserElement(): void {
		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('libresign_user_element')
				->executeStatement();
		} catch (\Throwable) {
		}
	}

	private function resetConfig(): void {
		try {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('appconfig')
				->where($delete->expr()->eq('appid', $delete->createNamedParameter(Application::APP_ID)))
				->andWhere($delete->expr()->notIn('configkey', $delete->createNamedParameter(['enabled', 'installed_version'], IQueryBuilder::PARAM_STR_ARRAY)))
				->executeStatement();
		} catch (\Throwable) {
		}
	}
}
