<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics\Model;

/**
 * A collector answer for one metric: either a value or an explicit statement
 * that the metric cannot be known for the requested period.
 */
final class MetricValue {
	private function __construct(
		private readonly bool $available,
		private readonly int|bool|string|null $value,
	) {
	}

	public static function of(int|bool|string $value): self {
		return new self(true, $value);
	}

	public static function unavailable(): self {
		return new self(false, null);
	}

	public function isAvailable(): bool {
		return $this->available;
	}

	public function value(): int|bool|string|null {
		return $this->value;
	}
}
