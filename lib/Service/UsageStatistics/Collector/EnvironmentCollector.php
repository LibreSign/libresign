<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics\Collector;

use OCA\Libresign\Service\Install\InstallTarget;
use OCA\Libresign\Service\UsageStatistics\Contract\IMetricCollector;
use OCA\Libresign\Service\UsageStatistics\Model\MetricValue;
use OCA\Libresign\Service\UsageStatistics\Model\ReportMode;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use OCA\Libresign\Service\UsageStatistics\Source\RuntimeEnvironment;
use OCA\Libresign\Service\UsageStatistics\UsageStatisticsException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IUserManager;

/**
 * Current state of the Nextcloud server and its runtime. There is no record
 * of these values in the past, so historical reports omit all of them.
 */
class EnvironmentCollector implements IMetricCollector {
	private const ACTIVE_USERS_DAYS = 30;
	// Nextcloud returns at most 100 users per call, whatever the limit asked.
	private const LAST_LOGIN_BATCH_SIZE = 100;
	private const BACKGROUND_JOB_MODES = ['ajax', 'webcron', 'cron'];

	public function __construct(
		private IUserManager $userManager,
		private IAppConfig $appConfig,
		private ITimeFactory $timeFactory,
		private RuntimeEnvironment $runtime,
	) {
	}

	#[\Override]
	public function metricIds(): array {
		return [
			'nextcloud.version',
			'nextcloud.users_total',
			'nextcloud.users_active_30d',
			'nextcloud.background_job_mode',
			'php.version',
			'database.type',
			'database.version',
			'system.os_family',
			'system.architecture',
		];
	}

	#[\Override]
	public function collect(ReportPeriod $period, ReportMode $mode): array {
		if ($mode === ReportMode::HISTORICAL) {
			return array_fill_keys($this->metricIds(), MetricValue::unavailable());
		}

		$usersTotal = $this->userManager->countUsersTotal();
		$databaseVersion = $this->databaseVersion();
		return [
			'nextcloud.version' => MetricValue::of($this->runtime->nextcloudVersion()),
			// Some user backends cannot count their users.
			'nextcloud.users_total' => $usersTotal === false ? MetricValue::unavailable() : MetricValue::of($usersTotal),
			'nextcloud.users_active_30d' => MetricValue::of($this->countRecentlyActiveUsers()),
			'nextcloud.background_job_mode' => MetricValue::of($this->backgroundJobMode()),
			'php.version' => MetricValue::of($this->runtime->phpVersion()),
			'database.type' => MetricValue::of($this->runtime->databasePlatform()),
			'database.version' => $databaseVersion === null ? MetricValue::unavailable() : MetricValue::of($databaseVersion),
			'system.os_family' => MetricValue::of($this->runtime->osFamily()),
			'system.architecture' => MetricValue::of($this->architecture()),
		];
	}

	/**
	 * Users come sorted by their last login, most recent first, so counting
	 * stops at the first one outside the window. Pages advance by what each
	 * call returned and end on an empty one, so a server that caps the batch
	 * below the requested size cannot end the count early.
	 */
	private function countRecentlyActiveUsers(): int {
		$since = $this->timeFactory->getTime() - self::ACTIVE_USERS_DAYS * 86400;
		$count = 0;
		$offset = 0;
		while (($userIds = $this->userManager->getLastLoggedInUsers(self::LAST_LOGIN_BATCH_SIZE, $offset)) !== []) {
			foreach ($userIds as $userId) {
				$user = $this->userManager->get($userId);
				if ($user === null) {
					continue;
				}
				if ($user->getLastLogin() < $since) {
					return $count;
				}
				$count++;
			}
			$offset += count($userIds);
		}
		return $count;
	}

	private function backgroundJobMode(): string {
		$mode = $this->appConfig->getValueString('core', 'backgroundjobs_mode', 'ajax');
		return in_array($mode, self::BACKGROUND_JOB_MODES, true) ? $mode : 'other';
	}

	/**
	 * Only major.minor: full version strings carry build and distribution details.
	 */
	private function databaseVersion(): ?string {
		$version = $this->runtime->databaseServerVersion();
		if ($version === null) {
			return null;
		}
		if (preg_match('/^(\d+)\.(\d+)/', trim($version), $matches) !== 1) {
			throw new UsageStatisticsException('Unrecognized database version format');
		}
		return $matches[1] . '.' . $matches[2];
	}

	private function architecture(): string {
		try {
			return InstallTarget::normalizeArchitecture($this->runtime->machine());
		} catch (\InvalidArgumentException) {
			return 'other';
		}
	}
}
