<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Envelope;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignRequestStatus;

class EnvelopeStatusDeterminer {
	/**
	 * A signer who rejected will not sign, so the envelope is over once everybody
	 * else signed: complete when at least one signature was applied, canceled when
	 * every signer rejected.
	 *
	 * @param FileEntity[] $childFiles
	 * @param array<int, array> $signRequestsMap
	 */
	public function determineStatus(array $childFiles, array $signRequestsMap): int {
		$totalSignRequests = 0;
		$signedSignRequests = 0;
		$rejectedSignRequests = 0;

		foreach ($childFiles as $childFile) {
			$signRequests = $signRequestsMap[$childFile->getId()] ?? [];

			foreach ($signRequests as $signRequest) {
				if ($signRequest->isObserver()) {
					continue;
				}

				$totalSignRequests++;

				if ($signRequest->getSigned()) {
					$signedSignRequests++;
				} elseif ($signRequest->getStatusEnum() === SignRequestStatus::REJECTED) {
					$rejectedSignRequests++;
				}
			}
		}

		if ($totalSignRequests === 0) {
			return FileStatus::DRAFT->value;
		}

		if ($signedSignRequests + $rejectedSignRequests === $totalSignRequests) {
			return $signedSignRequests > 0
				? FileStatus::SIGNED->value
				: FileStatus::CANCELED->value;
		}

		if ($signedSignRequests === 0) {
			return FileStatus::ABLE_TO_SIGN->value;
		}

		return FileStatus::PARTIAL_SIGNED->value;
	}
}
