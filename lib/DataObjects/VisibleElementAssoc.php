<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\DataObjects;

use OCA\Libresign\Db\FileElement;

class VisibleElementAssoc {
	public function __construct(
		private FileElement $fileElement,
		private string $tempFile = '',
	) {
	}

	public function getFileElement(): FileElement {
		return $this->fileElement;
	}

	public function getTempFile(): string {
		return $this->tempFile;
	}
}
