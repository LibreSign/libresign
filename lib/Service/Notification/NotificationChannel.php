<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

/** Transport acceptance does not imply that the recipient has read the message. */
interface NotificationChannel {
	public function supports(string $channel): bool;

	/** @throws \Exception Transport/preparation failure; unknown results must never be retried automatically. */
	public function deliver(NotificationMessage $message): void;
}
