<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

enum SignRequestStatus: int {
	case DRAFT = 0;
	case ABLE_TO_SIGN = 1;
	case SIGNED = 2;
}
