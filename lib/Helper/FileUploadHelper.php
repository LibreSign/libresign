<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Helper;

use InvalidArgumentException;
use OCP\Files\IFilenameValidator;
use OCP\IL10N;

/**
 * Helper for validating and processing uploaded files
 */
class FileUploadHelper {
	public function __construct(
		private IL10N $l10n,
		private IFilenameValidator $filenameValidator,
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
			// TRANSLATORS Validation error shown when the browser upload did not produce a usable temporary file.
			throw new InvalidArgumentException($this->l10n->t('The uploaded file is invalid'));
		}

		if (!is_uploaded_file($uploadedFile['tmp_name'])) {
			@unlink($uploadedFile['tmp_name']);
			// TRANSLATORS Validation error shown when the received file was not uploaded through the expected HTTP upload flow.
			throw new InvalidArgumentException($this->l10n->t('The uploaded file is invalid'));
		}

		if ($uploadedFile['size'] > \OCP\Util::uploadLimit()) {
			@unlink($uploadedFile['tmp_name']);
			// TRANSLATORS Validation error shown when an uploaded file is larger than the server's configured upload limit.
			throw new InvalidArgumentException($this->l10n->t('The uploaded file is too large'));
		}

		if (!$this->filenameValidator->isFilenameValid(basename((string)$uploadedFile['tmp_name']))) {
			@unlink($uploadedFile['tmp_name']);
			// TRANSLATORS Validation error shown when the temporary upload filename fails security validation.
			throw new InvalidArgumentException($this->l10n->t('The uploaded file is invalid'));
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
		$content = @file_get_contents($uploadedFile['tmp_name']);
		if ($content === false) {
			// TRANSLATORS Error shown when LibreSign cannot read the temporary file contents after upload.
			throw new InvalidArgumentException($this->l10n->t('Cannot read the uploaded file'));
		}
		return $content;
	}
}
