<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Exception;

use OCA\Libresign\Enum\NotificationDeliveryState;

/** A channel-independent transport result. Do not log provider response bodies. */
class NotificationTransportException extends \RuntimeException {
	public function __construct(
		string $message,
		public readonly NotificationDeliveryState $outcome,
		public readonly string $reasonCode,
		?\Throwable $previous = null,
	) {
		parent::__construct($message, 0, $previous);
	}
}
