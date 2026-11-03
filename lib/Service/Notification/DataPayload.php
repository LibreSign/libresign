<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

/**
 * Structured transport data for extensions that do not need a built-in
 * payload type. Only JSON primitives and arrays may be queued, never
 * PHP objects, provider credentials, or authentication codes.
 *
 * The module interprets its own schema; the dispatcher remains independent.
 */
final readonly class DataPayload implements NotificationPayload {
	/** @param array<array-key, mixed> $data */
	public function __construct(
		public array $data,
	) {
		self::assertSerializable($data, 0);
	}

	private static function assertSerializable(mixed $value, int $depth): void {
		if ($depth > 8) {
			throw new \InvalidArgumentException('Notification payload nesting limit exceeded');
		}
		if (is_array($value)) {
			foreach ($value as $part) {
				self::assertSerializable($part, $depth + 1);
			}
			return;
		}
		if ($value === null || is_string($value) || is_int($value) || is_bool($value)) {
			return;
		}
		if (is_float($value) && is_finite($value)) {
			return;
		}
		throw new \InvalidArgumentException('Notification payload must contain only JSON values');
	}
}
