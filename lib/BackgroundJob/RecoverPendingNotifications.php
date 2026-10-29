<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;

/** Restore due jobs lost after a crash, without transmitting or replaying uncertain results. */
final class RecoverPendingNotifications extends TimedJob {
	private const BATCH_SIZE = 50;
	private const MAX_INSPECTED = 500;
	private const MAX_QUEUED = 50;
	private const CURSOR_KEY = 'notification_recovery_last_scanned_id';

	public function __construct(
		ITimeFactory $time,
		private NotificationDeliveryMapper $deliveries,
		private IJobList $jobs,
		private IAppConfig $appConfig,
	) {
		parent::__construct($time);
		$this->setInterval(60);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	#[\Override]
	public function run($argument): void {
		$cursor = max(0, $this->appConfig->getValueInt(Application::APP_ID, self::CURSOR_KEY, 0));
		$inspected = 0;
		$queued = 0;
		while ($inspected < self::MAX_INSPECTED && $queued < self::MAX_QUEUED) {
			$limit = min(self::BATCH_SIZE, self::MAX_INSPECTED - $inspected);
			$batch = $this->deliveries->findDue($limit, $cursor);
			if ($batch === []) {
				$cursor = 0;
				break;
			}
			foreach ($batch as $delivery) {
				$cursor = $delivery->getId();
				$inspected++;
				$jobArgument = ['id' => $cursor];
				// Nextcloud 36 add() resets an existing job's reservation.
				if (!$this->jobs->has(DeliverNotification::class, $jobArgument)) {
					$this->jobs->add(DeliverNotification::class, $jobArgument);
					$queued++;
					if ($queued === self::MAX_QUEUED) {
						break;
					}
				}
			}
			if (count($batch) < $limit && $queued < self::MAX_QUEUED) {
				// End of the eligible ID range; next cron pass starts at the oldest.
				$cursor = 0;
				break;
			}
		}
		// Keep progress across cron processes even when the first 500 due rows
		// already have queued jobs. No delivery state or payload is stored here.
		$this->appConfig->setValueInt(Application::APP_ID, self::CURSOR_KEY, $cursor);
	}
}
