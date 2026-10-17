<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics\Model;

/**
 * A calendar month in UTC, start-inclusive and end-exclusive.
 */
final class ReportPeriod {
	private function __construct(
		private readonly \DateTimeImmutable $start,
		private readonly \DateTimeImmutable $end,
	) {
	}

	public static function monthContaining(\DateTimeInterface $instant): self {
		$utc = \DateTimeImmutable::createFromInterface($instant)->setTimezone(new \DateTimeZone('UTC'));
		$start = $utc->setDate((int)$utc->format('Y'), (int)$utc->format('n'), 1)->setTime(0, 0);
		return new self($start, $start->modify('+1 month'));
	}

	public function start(): \DateTimeImmutable {
		return $this->start;
	}

	public function end(): \DateTimeImmutable {
		return $this->end;
	}

	public function contains(\DateTimeInterface $instant): bool {
		return $instant >= $this->start && $instant < $this->end;
	}

	public function next(): self {
		return new self($this->end, $this->end->modify('+1 month'));
	}
}
