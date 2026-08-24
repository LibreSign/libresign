<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Controller\Traits;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;

/**
 * Trait for validating file uploads in controllers
 *
 * Provides reusable file upload validation logic with localized error messages
 */
trait UploadValidator {
	protected IL10N $l10n;

	/**
	 * Validate uploaded file and return error response if invalid
	 *
	 * @param array<string, mixed>|null $uploadedFile File array from IRequest::getUploadedFile()
	 * @param string $context Description for error messages (e.g., 'image', 'pdf')
	 * @return DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string, status: 'failure'}, array<never, never>>|null DataResponse with error if invalid, null if valid
	 */
	private function validateUploadedFile(?array $uploadedFile, string $context): ?DataResponse {
		$phpFileUploadErrors = [
			// TRANSLATORS PHP upload status message mapped from UPLOAD_ERR_OK when LibreSign receives a file upload.
			UPLOAD_ERR_OK => $this->l10n->t('The file was uploaded'),
			// TRANSLATORS PHP upload error mapped from UPLOAD_ERR_INI_SIZE; the file exceeds the server php.ini upload_max_filesize limit.
			UPLOAD_ERR_INI_SIZE => $this->l10n->t('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
			// TRANSLATORS PHP upload error mapped from UPLOAD_ERR_FORM_SIZE; the file exceeds the HTML form MAX_FILE_SIZE limit.
			UPLOAD_ERR_FORM_SIZE => $this->l10n->t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
			// TRANSLATORS PHP upload error mapped from UPLOAD_ERR_PARTIAL; only part of the file reached the server.
			UPLOAD_ERR_PARTIAL => $this->l10n->t('The file was only partially uploaded'),
			// TRANSLATORS PHP upload error mapped from UPLOAD_ERR_NO_FILE; the request contained no file.
			UPLOAD_ERR_NO_FILE => $this->l10n->t('No file was uploaded'),
			// TRANSLATORS PHP upload error mapped from UPLOAD_ERR_NO_TMP_DIR; the server temporary folder for uploads is missing.
			UPLOAD_ERR_NO_TMP_DIR => $this->l10n->t('Missing a temporary folder'),
			// TRANSLATORS PHP upload error mapped from UPLOAD_ERR_CANT_WRITE; the server could not write the uploaded file to disk.
			UPLOAD_ERR_CANT_WRITE => $this->l10n->t('Could not write file to disk'),
			// TRANSLATORS PHP upload error mapped from UPLOAD_ERR_EXTENSION; a PHP extension aborted the upload.
			UPLOAD_ERR_EXTENSION => $this->l10n->t('A PHP extension stopped the file upload'),
		];

		if (empty($uploadedFile)) {
			return new DataResponse(
				[
					// TRANSLATORS API validation error when LibreSign expected an uploaded file and none was present in the request.
					'message' => $this->l10n->t('No file uploaded'),
					'status' => 'failure',
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		if (!empty($uploadedFile) && array_key_exists('error', $uploadedFile) && $uploadedFile['error'] !== UPLOAD_ERR_OK) {
			return new DataResponse(
				[
					'message' => $phpFileUploadErrors[$uploadedFile['error']],
					'status' => 'failure',
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		return null;
	}
}
