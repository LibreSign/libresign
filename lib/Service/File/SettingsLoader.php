<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\File;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\ResponseDefinitions;
use OCA\Libresign\Service\IdDocsPolicyService;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use stdClass;

/**
 * @psalm-import-type LibresignSettings from ResponseDefinitions
 */
class SettingsLoader {
	public const IDENTIFICATION_DOCUMENTS_DISABLED = 0;
	public const IDENTIFICATION_DOCUMENTS_NEED_SEND = 1;
	public const IDENTIFICATION_DOCUMENTS_NEED_APPROVAL = 2;
	public const IDENTIFICATION_DOCUMENTS_APPROVED = 3;

	public function __construct(
		private AccountSettingsProvider $accountSettingsProvider,
		private IdDocsPolicyService $idDocsPolicyService,
		private IAppConfig $appConfig,
		private IGroupManager $groupManager,
		private IdDocsMapper $idDocsMapper,
		private IdentifyMethodService $identifyMethodService,
	) {
	}

	public function loadSettings(
		stdClass $fileData,
		FileResponseOptions $options,
	): void {
		if (!$options->isShowSettings()) {
			return;
		}

		$fileData->settings = $this->getUserIdentificationSettings($options->getMe(), $options->getSignRequest());

		if ($options->getMe()) {
			$fileData->settings = array_merge(
				$fileData->settings,
				$this->accountSettingsProvider->getSettings($options->getMe()),
			);
			$fileData->settings['phoneNumber'] = $this->accountSettingsProvider->getPhoneNumber($options->getMe());
			if ($this->idDocsPolicyService->canApproverSignIdDoc(
				$options->getMe(),
				$fileData->id,
				$fileData->status,
			)) {
				$fileData->settings['canSign'] = true;
				$fileData->settings['isApprover'] = true;
				$this->loadApproverSignatureMethods($fileData);
			}
		}
	}

	private function loadApproverSignatureMethods(stdClass $fileData): void {
		try {
			$idDocs = $this->idDocsMapper->getByFileId($fileData->id);
			$signRequestId = $idDocs->getSignRequestId();
			if (!$signRequestId) {
				return;
			}

			$signatureMethods = $this->identifyMethodService->getSignMethodsOfIdentifiedFactors($signRequestId);
			$fileData->settings['signatureMethods'] = $signatureMethods;
		} catch (\Throwable) {
		}
	}

	public function getIdentificationDocumentsStatus(?IUser $user = null, ?SignRequest $signRequest = null): int {
		if (!$this->appConfig->getValueBool(Application::APP_ID, 'identification_documents', false)) {
			return self::IDENTIFICATION_DOCUMENTS_DISABLED;
		}

		$approvalGroups = $this->appConfig->getValueArray(Application::APP_ID, 'approval_group', ['admin']);
		if ($user && !empty($approvalGroups) && is_array($approvalGroups)) {
			$userGroups = $this->groupManager->getUserGroupIds($user);
			if (array_intersect($userGroups, $approvalGroups)) {
				return self::IDENTIFICATION_DOCUMENTS_APPROVED;
			}
		}

		$files = $this->getIdDocFiles($user, $signRequest);

		return $this->calculateStatusFromFiles($files);
	}

	private function getIdDocFiles(?IUser $user, ?SignRequest $signRequest): ?array {
		if ($user) {
			return $this->idDocsMapper->getFilesOfAccount($user->getUID());
		}

		if ($signRequest) {
			return $this->idDocsMapper->getFilesOfSignRequest($signRequest->getId());
		}

		return null;
	}

	private function calculateStatusFromFiles(?array $files): int {
		if (empty($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_SEND;
		}

		$deleted = array_filter($files, fn (File $file) => $file->getStatus() === FileStatus::DELETED->value);
		if (count($deleted) === count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_SEND;
		}

		$signed = array_filter($files, fn (File $file) => $file->getStatus() === FileStatus::SIGNED->value);
		if (count($signed) !== count($files)) {
			return self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL;
		}

		return self::IDENTIFICATION_DOCUMENTS_APPROVED;
	}

	/**
	 * Get user identification documents settings
	 * These are user-specific settings, not file-specific
	 * Always returns complete LibresignSettings with defaults
	 *
	 * @psalm-return LibresignSettings
	 */
	public function getUserIdentificationSettings(?IUser $user, ?SignRequest $signRequest = null): array {
		$status = $this->getIdentificationDocumentsStatus($user, $signRequest);

		return [
			'canSign' => false,
			'canRequestSign' => false,
			'signerFileUuid' => null,
			'phoneNumber' => '',
			'hasSignatureFile' => false,
			'needIdentificationDocuments' => in_array($status, [
				self::IDENTIFICATION_DOCUMENTS_NEED_SEND,
				self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL,
			], true),
			'identificationDocumentsWaitingApproval' => $status === self::IDENTIFICATION_DOCUMENTS_NEED_APPROVAL,
		];
	}
}
