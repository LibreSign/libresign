<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Helper;

use InvalidArgumentException;
use OC\Files\FilenameValidator;
use OCP\IL10N;

/**
 * Helper for validating and processing uploaded files
 */
class FileUploadHelper {
	public function __construct(
		private IL10N $l10n,
	) {
	}

	/**
	 * Validate uploaded file from $_FILES
	 *
	 * @param array $uploadedFile Single file from $_FILES (e.g., $_FILES['file'])
	 * @throws InvalidArgumentException
	 */
	public function validateUploadedFile(array $uploadedFile): void {
		if ($uploadedFile['error'] !== 0) {
			@unlink($uploadedFile['tmp_name']);
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided'));
		}

		if (!is_uploaded_file($uploadedFile['tmp_name'])) {
			@unlink($uploadedFile['tmp_name']);
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided'));
		}

		$validator = \OCP\Server::get(FilenameValidator::class);
		if ($validator->isForbidden($uploadedFile['tmp_name'])) {
			@unlink($uploadedFile['tmp_name']);
			throw new InvalidArgumentException($this->l10n->t('Invalid file provided'));
		}

		if ($uploadedFile['size'] > \OCP\Util::uploadLimit()) {
			@unlink($uploadedFile['tmp_name']);
			throw new InvalidArgumentException($this->l10n->t('File is too big'));
		}
	}

	/**
	 * Read content from uploaded file
	 *
	 * @param array $uploadedFile Single file from $_FILES
	 * @return string File content
	 * @throws InvalidArgumentException
	 */
	public function readUploadedFile(array $uploadedFile): string {
		$content = file_get_contents($uploadedFile['tmp_name']);
		if ($content === false) {
			throw new InvalidArgumentException($this->l10n->t('Cannot read file'));
		}
		return $content;
	}
}
