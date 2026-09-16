<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

enum SignerIpGeolocationUnavailableReason: string {
	case DATABASE_NOT_READY = 'database_not_ready';
	case ADDRESS_UNAVAILABLE = 'address_unavailable';
	case LOOKUP_FAILED = 'lookup_failed';
}
