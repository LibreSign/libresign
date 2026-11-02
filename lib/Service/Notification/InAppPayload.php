<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCP\Notification\INotification;

final readonly class InAppPayload implements NotificationPayload {
	public function __construct(
		public INotification $notification,
		/** @var array<string, mixed> */
		public array $subjectParameters = [],
	) {
	}
}
