<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;

class IdDocsService {

	public function __construct(
		private IL10N $l10n,
		private FileTypeMapper $fileTypeMapper,
		private ValidateHelper $validateHelper,
		private RequestSignatureService $requestSignatureService,
		private IdDocsMapper $idDocsMapper,
		private AccountFileService $accountFileService,
		private IAppConfig $appConfig,
	) {
	}

	private function validateTypeOfFile(int $fileIndex, array $file): void {
		$profileFileTypes = $this->fileTypeMapper->getTypes();
		if (!array_key_exists($file['type'], $profileFileTypes)) {
			throw new LibresignException(json_encode([
				'type' => 'danger',
				'file' => $fileIndex,
				'message' => $this->l10n->t('Invalid file type.')
			]));
		}
	}

	private function validateAccountFile(int $fileIndex, array $file, IUser $user): void {
		$profileFileTypes = $this->fileTypeMapper->getTypes();
		if (!array_key_exists($file['type'], $profileFileTypes)) {
			throw new LibresignException(json_encode([
				'type' => 'danger',
				'file' => $fileIndex,
				'message' => $this->l10n->t('Invalid file type.')
			]));
		}

		try {
			$this->validateHelper->validateFileTypeExists($file['type']);
			$this->validateHelper->validateNewFile($file, ValidateHelper::TYPE_ACCOUNT_DOCUMENT, $user);
			$this->validateHelper->validateUserHasNoFileWithThisType($user->getUID(), $file['type']);
		} catch (\Exception $e) {
			throw new LibresignException(json_encode([
				'type' => 'danger',
				'file' => $fileIndex,
				'message' => $e->getMessage()
			]));
		}
	}

	public function validateAccountFiles(array $files, IUser $user): void {
		foreach ($files as $fileIndex => $file) {
			$this->validateTypeOfFile($fileIndex, $file);
			$this->validateAccountFile($fileIndex, $file, $user);
		}
	}

	public function addFilesToAccount(array $files, IUser $user): void {
		$this->validateAccountFiles($files, $user);
		foreach ($files as $fileData) {
			$dataToSave = $fileData;
			$dataToSave['userManager'] = $user;
			$dataToSave['name'] = $fileData['name'] ?? $fileData['type'];
			$file = $this->requestSignatureService->saveFile($dataToSave);

			$this->accountFileService->addFile($file, $user, $fileData['type']);
		}
	}

	public function addFilesToDocumentFolder(array $files, SignRequest $signRequest): void {
		foreach ($files as $fileIndex => $file) {
			$this->validateTypeOfFile($fileIndex, $file);
		}
		foreach ($files as $fileData) {
			$dataToSave = $fileData;
			$dataToSave['signRequest'] = $signRequest;
			$dataToSave['name'] = $fileData['name'] ?? $fileData['type'];
			$file = $this->requestSignatureService->saveFile($dataToSave);

			$this->accountFileService->addFile($file, $user, $fileData['type']);
		}
	}

	public function list(array $filter, ?int $page = null, ?int $length = null): array {
		$page = $page ?? 1;
		$length = $length ?? (int)$this->appConfig->getValueInt(Application::APP_ID, 'length_of_page', 100);
		$data = $this->idDocsMapper->list($filter, $page, $length);
		$data['pagination']->setRouteName('ocs.libresign.File.list');
		return [
			'data' => $data['data'],
			'pagination' => $data['pagination']->getPagination($page, $length, $filter)
		];
	}

	public function deleteFileFromAccount(int $nodeId, IUser $user): void {
		$this->validateHelper->validateAccountFileIsOwnedByUser($nodeId, $user->getUID());
		$accountFile = $this->accountFileMapper->getByUserIdAndNodeId($user->getUID(), $nodeId);
		$this->accountFileService->deleteFile($accountFile->getFileId(), $user->getUID());
	}
}
