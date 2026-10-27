<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Service\Notification\NotificationChannel;
use OCA\Libresign\Service\Notification\NotificationDeliveryRegistrar;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\Notification\NotificationSubmissionService;

/** Synchronous delivery facade. Registration and transport attempts stay independent. */
class NotificationDeliveryService {
	public function __construct(
		private NotificationDeliveryRegistrar $registrar,
		private NotificationSubmissionService $submission,
	) {
	}

	/** @return bool Whether this invocation claimed and attempted delivery. */
	public function dispatch(NotificationMessage $message, NotificationChannel $channel): bool {
		$delivery = $this->registrar->register($message, $channel);
		return $this->submission->submit($delivery, $message, $channel);
	}
}
