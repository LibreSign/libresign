<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

/**
 * ACCEPTED means the channel adapter accepted the operation.
 * It never proves reading or mailbox delivery. UNKNOWN is not safe to retry.
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
