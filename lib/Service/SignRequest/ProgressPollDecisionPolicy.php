<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignRequest;

class ProgressPollDecisionPolicy {
	public function normalizeCachedStatus(mixed $cachedStatus): ?int {
		return ($cachedStatus !== false && $cachedStatus !== null) ? (int)$cachedStatus : null;
	}

	public function initialStatusFromCache(?int $cachedStatus, int $initialStatus): ?int {
		if ($cachedStatus === null) {
			return null;
		}

		return $cachedStatus !== $initialStatus ? $cachedStatus : null;
	}

	public function statusFromCacheChange(?int $previousStatus, ?int $currentStatus): ?int {
		if ($currentStatus === null || $currentStatus === $previousStatus) {
			return null;
		}
		return $currentStatus;
	}

	public function statusFromProgressChange(?int $currentStatus, ?int $previousStatus, int $initialStatus): int {
		if ($currentStatus !== null) {
			return $currentStatus;
		}
		if ($previousStatus !== null) {
			return $previousStatus;
		}
		return $initialStatus;
	}
}
