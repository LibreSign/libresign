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
	private function validateUploadedFile(?array $uploadedFile, string $context): DataResponse|null {
		$phpFileUploadErrors = [
			UPLOAD_ERR_OK => $this->l10n->t('The file was uploaded'),
			UPLOAD_ERR_INI_SIZE => $this->l10n->t('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
			UPLOAD_ERR_FORM_SIZE => $this->l10n->t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
			UPLOAD_ERR_PARTIAL => $this->l10n->t('The file was only partially uploaded'),
			UPLOAD_ERR_NO_FILE => $this->l10n->t('No file was uploaded'),
			UPLOAD_ERR_NO_TMP_DIR => $this->l10n->t('Missing a temporary folder'),
			UPLOAD_ERR_CANT_WRITE => $this->l10n->t('Could not write file to disk'),
			UPLOAD_ERR_EXTENSION => $this->l10n->t('A PHP extension stopped the file upload'),
		];

		if (empty($uploadedFile)) {
			return new DataResponse(
				[
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
