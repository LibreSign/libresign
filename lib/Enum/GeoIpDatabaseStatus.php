<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

enum GeoIpDatabaseStatus: string {
	case NOT_CONFIGURED = 'not_configured';
	case NOT_FOUND = 'not_found';
	case NOT_READABLE = 'not_readable';
	case INVALID_DATABASE = 'invalid_database';
	case UNSUPPORTED_DATABASE = 'unsupported_database';
	case READY = 'ready';
}
