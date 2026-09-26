<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

enum SignerIpGeolocationMode: string {
	case DISABLED = 'disabled';
	case ENABLED = 'enabled';
}
