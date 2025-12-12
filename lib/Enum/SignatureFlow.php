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

	/**
	 * Convert enum to numeric value for database storage
	 * @return int 1 for PARALLEL, 2 for ORDERED_NUMERIC
	 */
	public function toNumeric(): int {
		return match($this) {
			self::PARALLEL => 1,
			self::ORDERED_NUMERIC => 2,
		};
	}

	/**
	 * Create enum from numeric database value
	 * @param int $value 1 for PARALLEL, 2 for ORDERED_NUMERIC
	 * @return self
	 * @throws \ValueError if value is invalid
	 */
	public static function fromNumeric(int $value): self {
		return match($value) {
			1 => self::PARALLEL,
			2 => self::ORDERED_NUMERIC,
			default => throw new \ValueError("Invalid numeric value for SignatureFlow: $value"),
		};
	}
}
