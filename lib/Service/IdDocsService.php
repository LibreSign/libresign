<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\FileTypeMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use Sabre\DAV\UUIDUtil;

class IdDocsService {

	public function __construct(
		private IL10N $l10n,
		private FileTypeMapper $fileTypeMapper,
		private ValidateHelper $validateHelper,
		private RequestSignatureService $requestSignatureService,
		private IdDocsMapper $idDocsMapper,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private IdentifyMethodMapper $identifyMethodMapper,
		private ITimeFactory $timeFactory,
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

	private function validateIdDoc(int $fileIndex, array $file, IUser $user): void {
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

	public function validateIdDocs(array $files, IUser $user): void {
		foreach ($files as $fileIndex => $file) {
			$this->validateTypeOfFile($fileIndex, $file);
			$this->validateIdDoc($fileIndex, $file, $user);
		}
	}

	public function addIdDocs(array $files, IUser $user): void {
		$this->validateIdDocs($files, $user);
		foreach ($files as $fileData) {
			$dataToSave = $fileData;
			$dataToSave['userManager'] = $user;
			$dataToSave['name'] = $fileData['name'] ?? $fileData['type'];
			$file = $this->requestSignatureService->saveFile($dataToSave);

			$signRequest = new SignRequestEntity();
			$signRequest->setFileId($file->getId());
			$signRequest->setDisplayName($user->getDisplayName());
			$signRequest->setUuid(UUIDUtil::getUUID());
			$signRequest->setCreatedAt($this->timeFactory->getDateTime());
			$this->signRequestMapper->insert($signRequest);

			$identifyMethod = new IdentifyMethod();
			$identifyMethod->setSignRequestId($signRequest->getId());
			$identifyMethod->setIdentifierKey(IdentifyMethodService::IDENTIFY_ACCOUNT);
			$identifyMethod->setIdentifierValue($user->getUID());
			$identifyMethod->setMandatory(true);
			$this->identifyMethodMapper->insert($identifyMethod);

			$this->idDocsMapper->save($file->getId(), $signRequest->getId(), $user->getUID(), $fileData['type']);
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

			$this->idDocsMapper->save($file->getId(), $signRequest->getId(), null, $fileData['type']);
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

	public function deleteIdDoc(int $nodeId, IUser $user): void {
		$this->validateHelper->validateIdDocIsOwnedByUser($nodeId, $user->getUID());
		$idDocs = $this->idDocsMapper->getByUserIdAndNodeId($user->getUID(), $nodeId);
		$this->idDocsMapper->delete($idDocs);
		$file = $this->fileMapper->getById($idDocs->getFileId());
		$this->fileMapper->delete($file);
	}

	public function getIdDocsByUser(IUser $user): array {
		$idDocs = $this->idDocsMapper->getByUserId($user->getUID());
		$files = [];
		foreach ($idDocs as $idDoc) {
			$file = $this->fileMapper->getById($idDoc->getFileId());
			$files[] = [
				'nodeId' => $file->getNodeId(),
				'fileId' => $idDoc->getFileId(),
				'type' => $idDoc->getFileType(),
				'name' => $file->getName(),
			];
		}
		return $files;
	}
}
