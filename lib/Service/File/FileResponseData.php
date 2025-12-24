<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\Db\File;
use stdClass;

/**
 * Immutable data structure containing assembled file response data
 *
 * This object holds all data needed to format a file response.
 * It's created by FileService and consumed by FileResponseFormatter.
 */
class FileResponseData {
	public function __construct(
		private ?File $file,
		private stdClass $fileData,
	) {
	}

	public function getFile(): ?File {
		return $this->file;
	}

	public function getFileData(): stdClass {
		return $this->fileData;
	}
}
