<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Exception;

/** The notification was not submitted because required data was not persisted. */
final class NotificationPreparationException extends \RuntimeException {
	public function __construct(
		public readonly string $reasonCode,
		?\Throwable $previous = null,
	) {
		parent::__construct('Notification preparation failed before SMTP submission', 0, $previous);
	}
}
