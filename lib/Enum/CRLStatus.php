<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

enum CRLStatus: string {
	case ISSUED = 'issued';
	case REVOKED = 'revoked';

	public function isRevoked(): bool {
		return $this === self::REVOKED;
	}

	public function isIssued(): bool {
		return $this === self::ISSUED;
	}
}
