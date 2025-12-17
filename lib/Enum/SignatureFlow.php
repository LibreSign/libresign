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
	case NONE = 'none';
	case PARALLEL = 'parallel';
	case ORDERED_NUMERIC = 'ordered_numeric';

	public const NUMERIC_NONE = 0;
	public const NUMERIC_PARALLEL = 1;
	public const NUMERIC_ORDERED_NUMERIC = 2;

	public function toNumeric(): int {
		return match($this) {
			self::NONE => self::NUMERIC_NONE,
			self::PARALLEL => self::NUMERIC_PARALLEL,
			self::ORDERED_NUMERIC => self::NUMERIC_ORDERED_NUMERIC,
		};
	}

	public static function fromNumeric(int $value): self {
		return match($value) {
			self::NUMERIC_NONE => self::NONE,
			self::NUMERIC_PARALLEL => self::PARALLEL,
			self::NUMERIC_ORDERED_NUMERIC => self::ORDERED_NUMERIC,
			default => throw new \ValueError("Invalid numeric value for SignatureFlow: $value"),
		};
	}
}
