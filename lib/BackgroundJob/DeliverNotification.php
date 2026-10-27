<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\BackgroundJob;

use OCA\Libresign\Service\Notification\NotificationDeliveryProcessor;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

/** A job stores only the durable delivery ID, never message content. */
final class DeliverNotification extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private NotificationDeliveryProcessor $processor,
	) {
		parent::__construct($time);
	}

	#[\Override]
	public function run($argument): void {
		if (!is_array($argument) || !isset($argument['id']) || (int)$argument['id'] <= 0) {
			return;
		}
		$this->processor->process((int)$argument['id']);
	}
}
