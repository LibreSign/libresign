<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

/**
 * Formats assembled file data into array response
 *
 * This class takes pre-assembled data and converts it to the API response format.
 * It has no mutable state and no dependencies on repositories,
 * making it easy to test without mocks.
 */
class FileResponseFormatter {
	/**
	 * Format file response data to array
	 *
	 * @param FileResponseData $data Pre-assembled file data
	 * @return array The formatted response array
	 * @psalm-return array
	 */
	public function toArray(FileResponseData $data): array {
		$fileData = $data->getFileData();
		$return = json_decode(json_encode($fileData), true);
		ksort($return);
		return $return;
	}
}
