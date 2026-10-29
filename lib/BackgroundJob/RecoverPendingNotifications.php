<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCP\BackgroundJob\IJobList;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
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
		foreach ($this->deliveries->findDue(50) as $delivery) {
			$argument = ['id' => $delivery->getId()];
			// Nextcloud 36 add() resets an existing job's reservation.
			// Do not release another worker's lease during reconciliation.
			if (!$this->jobs->has(DeliverNotification::class, $argument)) {
				$this->jobs->add(DeliverNotification::class, $argument);
			}
		}
	}
}
