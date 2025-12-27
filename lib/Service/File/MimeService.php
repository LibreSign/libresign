<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\Helper\ValidateHelper;
use OCP\Files\IMimeTypeDetector;

class MimeService {
	private ?string $mimetype = null;

	public function __construct(
		private IMimeTypeDetector $mimeTypeDetector,
		private ValidateHelper $validateHelper,
	) {
	}

	/**
	 * Set and validate a MIME type
	 *
	 * @throws \Exception if MIME type is not accepted
	 */
	public function setMimeType(string $mimetype): void {
		$this->validateHelper->validateMimeTypeAcceptedByMime($mimetype);
		$this->mimetype = $mimetype;
	}

	public function getMimeType(string $content): string {
		if ($this->mimetype === null) {
			$detected = $this->mimeTypeDetector->detectString($content);
			$this->setMimeType($detected);
		}
		// After setMimeType, mimetype is guaranteed to be non-null
		// (or an exception was thrown)
		assert($this->mimetype !== null);
		return $this->mimetype;
	}

	public function getExtension(string $content): string {
		$mimetype = $this->getMimeType($content);
		$mappings = $this->mimeTypeDetector->getAllMappings();

		foreach ($mappings as $ext => $mimetypes) {
			$ext = (string)$ext;
			// Skip internal mappings starting with underscore
			if ($ext[0] === '_') {
				continue;
			}
			if (in_array($mimetype, $mimetypes)) {
				return $ext;
			}
		}

		return '';
	}

	public function reset(): void {
		$this->mimetype = null;
	}

	public function getCurrentMimeType(): ?string {
		return $this->mimetype;
	}
}
