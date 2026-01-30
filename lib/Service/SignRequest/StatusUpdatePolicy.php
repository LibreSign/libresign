<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\SignRequest;

use OCA\Libresign\Enum\SignRequestStatus;

class StatusUpdatePolicy {
	public function shouldUpdateStatus(
		SignRequestStatus $currentStatus,
		SignRequestStatus $desiredStatus,
		bool $isNewSignRequest,
		bool $isStatusUpgrade,
		bool $isOrderedNumericFlow,
		bool $hasPendingLowerOrderSigners,
	): bool {
		if ($isNewSignRequest) {
			return true;
		}

		if ($isStatusUpgrade) {
			return true;
		}

		return $desiredStatus === SignRequestStatus::DRAFT
			&& $currentStatus === SignRequestStatus::ABLE_TO_SIGN
			&& $isOrderedNumericFlow
			&& $hasPendingLowerOrderSigners;
	}
}
