<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

/**
 * Signature flow modes
 */
enum SignatureFlow: string {
	case PARALLEL = 'parallel';
	case ORDERED_NUMERIC = 'ordered_numeric';
}
