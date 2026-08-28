<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

enum SignerGeolocationCollectionStatus: string {
	case COLLECTED = 'collected';
	case DENIED = 'denied';
	case UNAVAILABLE = 'unavailable';
	case SKIPPED = 'skipped';
}
