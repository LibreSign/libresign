<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Policy\Provider\Reminder;

final class ReminderPolicyValue {
	/** @return array{days_before: int, days_between: int, max: int, send_timer: string} */
	public static function defaults(): array {
		return [
			'days_before' => 0,
			'days_between' => 0,
			'max' => 0,
			'send_timer' => '10:00',
		];
	}

	/** @return array{days_before: int, days_between: int, max: int, send_timer: string} */
	public static function normalize(mixed $rawValue): array {
		$defaults = self::defaults();

		if (is_string($rawValue)) {
			$trimmed = trim($rawValue);
			if ($trimmed !== '') {
				$decoded = json_decode($trimmed, true);
				if (is_array($decoded)) {
					$rawValue = $decoded;
				}
			}
		}

		if (!is_array($rawValue)) {
			return $defaults;
		}

		$daysBefore = self::toNonNegativeInt($rawValue['days_before'] ?? $defaults['days_before']);
		$daysBetween = self::toNonNegativeInt($rawValue['days_between'] ?? $defaults['days_between']);
		$max = self::toNonNegativeInt($rawValue['max'] ?? $defaults['max']);
		$sendTimer = self::normalizeSendTimer($rawValue['send_timer'] ?? $defaults['send_timer']);

		return [
			'days_before' => $daysBefore,
			'days_between' => $daysBetween,
			'max' => $max,
			'send_timer' => $sendTimer,
		];
	}

	/** @param array<string, mixed> $value */
	public static function encode(array $value): string {
		return (string)json_encode(self::normalize($value), JSON_UNESCAPED_SLASHES);
	}

	private static function toNonNegativeInt(mixed $rawValue): int {
		if (is_int($rawValue)) {
			return max(0, $rawValue);
		}

		if (is_numeric($rawValue)) {
			return max(0, (int)$rawValue);
		}

		return 0;
	}

	private static function normalizeSendTimer(mixed $rawValue): string {
		if (!is_scalar($rawValue)) {
			return '10:00';
		}

		$value = trim((string)$rawValue);
		if ($value === '') {
			return '';
		}

		if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
			return '10:00';
		}

		return $value;
	}
}
