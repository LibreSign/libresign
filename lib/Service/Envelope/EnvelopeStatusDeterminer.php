<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Envelope;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Enum\FileStatus;

/**
 * Determines envelope status based on child files and sign requests.
 *
 * Encapsulates the logic for deciding the envelope status:
 * - DRAFT: no sign requests
 * - ABLE_TO_SIGN: has sign requests but none are signed
 * - PARTIAL_SIGNED: some sign requests are signed
 * - SIGNED: all sign requests are signed
 */
class EnvelopeStatusDeterminer {
	/**
	 * Determine the target status for an envelope based on its children
	 *
	 * @param FileEntity[] $childFiles Child files of the envelope
	 * @param array $signRequestsMap Map of file ID to sign requests
	 * @return int Target status value
	 */
	public function determineStatus(array $childFiles, array $signRequestsMap): int {
		$totalSignRequests = 0;
		$signedSignRequests = 0;

		foreach ($childFiles as $childFile) {
			$signRequests = $signRequestsMap[$childFile->getId()] ?? [];
			$totalSignRequests += count($signRequests);

			foreach ($signRequests as $signRequest) {
				if ($signRequest->getSigned()) {
					$signedSignRequests++;
				}
			}
		}

		if ($totalSignRequests === 0) {
			return FileStatus::DRAFT->value;
		}

		if ($signedSignRequests === 0) {
			return FileStatus::ABLE_TO_SIGN->value;
		}

		if ($signedSignRequests === $totalSignRequests) {
			return FileStatus::SIGNED->value;
		}

		return FileStatus::PARTIAL_SIGNED->value;
	}
}
