<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Enum;

/**
 * Signature flow modes
 */
enum SignatureFlow: string {
	case PARALLEL = 'parallel';
	case ORDERED_NUMERIC = 'ordered_numeric';

	public function toNumeric(): int {
		return match($this) {
			self::PARALLEL => 1,
			self::ORDERED_NUMERIC => 2,
		};
	}

	public static function fromNumeric(int $value): self {
		return match($value) {
			1 => self::PARALLEL,
			2 => self::ORDERED_NUMERIC,
			default => throw new \ValueError("Invalid numeric value for SignatureFlow: $value"),
		};
	}
}
