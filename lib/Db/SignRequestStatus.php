<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

enum SignRequestStatus: string {
	case DRAFT = 'draft';
	case ABLE_TO_SIGN = 'able_to_sign';
	case SIGNED = 'signed';
}
