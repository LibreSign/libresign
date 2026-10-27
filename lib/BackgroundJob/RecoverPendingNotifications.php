<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Service\Notification\NotificationDeliveryProcessor;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

/** Reconcile a lost queued job or process interruption, without a broker. */
final class RecoverPendingNotifications extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private NotificationDeliveryMapper $deliveries,
		private NotificationDeliveryProcessor $processor,
	) {
		parent::__construct($time);
		$this->setInterval(60);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	#[\Override]
	public function run($argument): void {
		foreach ($this->deliveries->findPending(50) as $delivery) {
			$this->processor->process($delivery->getId());
		}
	}
}
