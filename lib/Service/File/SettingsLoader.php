<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCP\IAppConfig;
use stdClass;

class SettingsLoader {
	public const IDENTIFICATION_DOCUMENTS_DISABLED = 0;
	public const IDENTIFICATION_DOCUMENTS_NEED_SEND = 1;
	public const IDENTIFICATION_DOCUMENTS_NEED_APPROVAL = 2;
	public const IDENTIFICATION_DOCUMENTS_APPROVED = 3;

	public function __construct(
		private AccountSettingsProvider $accountSettingsProvider,
		private FileMapper $fileMapper,
		private IAppConfig $appConfig,
	) {
	}

	public function loadSettings(stdClass $fileData, FileResponseOptions $options): void {
		if (!$options->isShowSettings()) {
			return;
		}

		if ($options->getMe()) {
			$fileData->settings = array_merge(
				$fileData->settings ?? [],
				$this->accountSettingsProvider->getSettings($options->getMe())
			);
			$fileData->settings['phoneNumber'] = $this->accountSettingsProvider->getPhoneNumber($options->getMe());
		}

		if ($options->isSignerIdentified() || $options->getMe()) {
			$status = $this->getIdentificationDocumentsStatus(
				$options->getMe() ? $options->getMe()->getUID() : ''
			);

			if ($status === self::IDENTIFICATION_DOCUMENTS_NEED_SEND) {
				$fileData->settings['needIdentificationDocuments'] = true;
				$fileData->settings['identificationDocumentsWaitingApproval'] = false;
			} elseif ($status === self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL) {
				$fileData->settings['needIdentificationDocuments'] = true;
				$fileData->settings['identificationDocumentsWaitingApproval'] = true;
			}
		}
	}

	public function getIdentificationDocumentsStatus(string $userId = ''): int {
		if (!$this->appConfig->getValueBool(Application::APP_ID, 'identification_documents', false)) {
			return self::IDENTIFICATION_DOCUMENTS_DISABLED;
		}

		if (empty($userId)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_SEND;
		}

		$files = $this->fileMapper->getFilesOfAccount($userId);

		if (empty($files) || !count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_SEND;
		}

		$deleted = array_filter($files, fn (File $file) => $file->getStatus() === File::STATUS_DELETED);
		if (count($deleted) === count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_SEND;
		}

		$signed = array_filter($files, fn (File $file) => $file->getStatus() === File::STATUS_SIGNED);
		if (count($signed) !== count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL;
		}

		return self::IDENTIFICATION_DOCUMENTS_APPROVED;
	}
}
