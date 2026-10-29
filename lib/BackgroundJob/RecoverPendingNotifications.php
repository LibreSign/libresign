<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;

/** Reconcile a lost queued job or process interruption, without a broker. */
final class RecoverPendingNotifications extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private NotificationDeliveryMapper $deliveries,
		private IJobList $jobs,
	) {
		parent::__construct($time);
		$this->setInterval(60);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	#[\Override]
	public function run($argument): void {
		// Scan a bounded number of pages so jobs already in Nextcloud's queue
		// do not permanently starve later due deliveries.
		$queued = 0;
		for ($offset = 0; $offset < 500 && $queued < 50; $offset += 50) {
			$batch = $this->deliveries->findDue(50, $offset);
			foreach ($batch as $delivery) {
				$jobArgument = ['id' => $delivery->getId()];
				// Nextcloud 36 add() resets an existing job's reservation.
				if (!$this->jobs->has(DeliverNotification::class, $jobArgument)) {
					$this->jobs->add(DeliverNotification::class, $jobArgument);
					$queued++;
				}
			}
			if (count($batch) < 50) {
				break;
			}
		}
	}
}
