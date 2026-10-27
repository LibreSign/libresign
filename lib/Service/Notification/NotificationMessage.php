<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;

/** One intended delivery to one destination on one channel for a committed event. */
final readonly class NotificationMessage {
	public function __construct(
		public string $sourceEventId,
		public string $eventType,
		public string $channel,
		public File $file,
		public SignRequest $signRequest,
		public string $recipient,
		public NotificationPayload $payload,
	) {
	}
}
