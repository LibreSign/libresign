<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

/**
 * ACCEPTED means submission was accepted by the mail transport, not delivered
 * to a recipient's mailbox. UNKNOWN must never be retried automatically.
 */
enum NotificationDeliveryState: string {
	case PENDING = 'pending';
	case SENDING = 'sending';
	case ACCEPTED = 'accepted';
	case TEMPORARY_FAILURE = 'temporary_failure';
	case PERMANENT_FAILURE = 'permanent_failure';
	case FAILED = 'failed';
	case UNKNOWN = 'unknown';
	case CANCELED = 'canceled';
}
