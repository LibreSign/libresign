<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

/**
 * File status enum
 *
 * Represents all possible states a LibreSign file can be in
 */
enum FileStatus: int {
	case NOT_LIBRESIGN_FILE = -1;
	case DRAFT = 0;
	case ABLE_TO_SIGN = 1;
	case PARTIAL_SIGNED = 2;
	case SIGNED = 3;
	case DELETED = 4;
}
