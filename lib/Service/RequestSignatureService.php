<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileElementMapper;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdentifyMethodMapper;
use OCA\Libresign\Db\SignRequest as SignRequestEntity;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\SignatureFlow;
use OCA\Libresign\Events\SignRequestCanceledEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Helper\FileUploadHelper;
use OCA\Libresign\Helper\ValidateHelper;
use OCA\Libresign\Service\Envelope\EnvelopeFileRelocator;
use OCA\Libresign\Service\Envelope\EnvelopeService;
use OCA\Libresign\Service\File\Pdf\PdfMetadataExtractor;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\SignRequest\SignRequestService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\Node;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Sabre\DAV\UUIDUtil;

class RequestSignatureService {

	public function __construct(
		protected FileService $fileService,
		protected IL10N $l10n,
		protected IdentifyMethodService $identifyMethod,
		protected SignRequestMapper $signRequestMapper,
		protected IUserManager $userManager,
		protected FileMapper $fileMapper,
		protected IdentifyMethodMapper $identifyMethodMapper,
		protected PdfMetadataExtractor $pdfMetadataExtractor,
		protected FileElementService $fileElementService,
		protected FileElementMapper $fileElementMapper,
		protected FolderService $folderService,
		protected IMimeTypeDetector $mimeTypeDetector,
		protected ValidateHelper $validateHelper,
		protected IClientService $client,
		protected DocMdpHandler $docMdpHandler,
		protected LoggerInterface $logger,
		protected SequentialSigningService $sequentialSigningService,
		protected IAppConfig $appConfig,
		protected IEventDispatcher $eventDispatcher,
		protected FileStatusService $fileStatusService,
		protected DocMdpConfigService $docMdpConfigService,
		protected EnvelopeService $envelopeService,
		protected EnvelopeFileRelocator $envelopeFileRelocator,
		protected FileUploadHelper $uploadHelper,
		protected SignRequestService $signRequestService,
	) {
	}

	/**
	 * Save files - creates single file or envelope based on files count
	 *
	 * @return array{file: FileEntity, children: list<FileEntity>}
	 */
	public function saveFiles(array $data): array {
		if (empty($data['files'])) {
			throw new LibresignException('Files parameter is required');
		}

		if (count($data['files']) === 1) {
			$fileData = $data['files'][0];

			$saveData = [
				'name' => $data['name'] ?? $fileData['name'] ?? '',
				'userManager' => $data['userManager'],
				'status' => FileStatus::DRAFT->value,
				'settings' => $data['settings'],
			];

			if (isset($fileData['uploadedFile'])) {
				$saveData['uploadedFile'] = $fileData['uploadedFile'];
			} elseif (isset($fileData['fileNode'])) {
				$saveData['file'] = ['fileNode' => $fileData['fileNode']];
			} else {
				$saveData['file'] = $fileData;
			}

			$savedFile = $this->save($saveData);

			return [
				'file' => $savedFile,
				'children' => [$savedFile],
			];
		}

		$result = $this->saveEnvelope([
			'files' => $data['files'],
			'name' => $data['name'],
			'userManager' => $data['userManager'],
			'settings' => $data['settings'],
			'signers' => $data['signers'] ?? [],
			'status' => $data['status'] ?? FileStatus::DRAFT->value,
			'visibleElements' => $data['visibleElements'] ?? [],
			'signatureFlow' => $data['signatureFlow'] ?? null,
		]);

		return [
			'file' => $result['envelope'],
			'children' => $result['files'],
		];
	}

	public function save(array $data): FileEntity {
		$file = $this->saveFile($data);
		if (!isset($data['status'])) {
			$data['status'] = $file->getStatus();
		}
		$this->sequentialSigningService->setFile($file);
		$this->associateToSigners($data, $file);
		$this->propagateSignersToChildren($file, $data);
		$this->saveVisibleElements($data, $file);

		return $file;
	}

	private function propagateSignersToChildren(FileEntity $envelope, array $data): void {
		if ($envelope->getNodeType() !== 'envelope' || empty($data['signers'])) {
			return;
		}

		$children = $this->fileMapper->getChildrenFiles($envelope->getId());

		$dataWithoutNotification = $data;
		foreach ($dataWithoutNotification['signers'] as &$signer) {
			$signer['notify'] = 0;
		}

		foreach ($children as $child) {
			$this->identifyMethod->clearCache();
			$this->sequentialSigningService->setFile($child);
			$this->associateToSigners($dataWithoutNotification, $child);
		}

		if ($envelope->getStatus() > FileStatus::DRAFT->value) {
			$this->fileStatusService->propagateStatusToChildren($envelope->getId(), $envelope->getStatus());
		}
	}

	public function saveEnvelope(array $data): array {
		$this->envelopeService->validateEnvelopeConstraints(count($data['files']));

		$envelopeName = $data['name'] ?: $this->l10n->t('Envelope %s', [date('Y-m-d H:i:s')]);
		$userManager = $data['userManager'] ?? null;
		$userId = $userManager instanceof IUser ? $userManager->getUID() : null;
		$filesCount = count($data['files']);

		$envelope = null;
		$files = [];
		$createdNodes = [];

		try {
			$envelopePath = $data['settings']['path'] ?? null;
			$envelope = $this->envelopeService->createEnvelope($envelopeName, $userId, $filesCount, $envelopePath);

			$envelopeFolder = $this->envelopeService->getEnvelopeFolder($envelope);
			$envelopeSettings = array_merge($data['settings'] ?? [], [
				'envelopeFolderId' => $envelopeFolder->getId(),
			]);

			foreach ($data['files'] as $fileData) {
				$node = $this->processFileData($fileData, $userManager, $envelopeSettings);
				$createdNodes[] = $node;

				$fileData['node'] = $node;
				$fileEntity = $this->createFileForEnvelope($fileData, $userManager, $envelopeSettings);
				$this->envelopeService->addFileToEnvelope($envelope->getId(), $fileEntity);
				$files[] = $fileEntity;
			}

			if (!empty($data['signers'])) {
				$this->sequentialSigningService->setFile($envelope);
				$this->associateToSigners($data, $envelope);
				$this->propagateSignersToChildren($envelope, $data);
			}

			return [
				'envelope' => $envelope,
				'files' => $files,
			];
		} catch (\Throwable $e) {
			$this->rollbackEnvelopeCreation($envelope, $files, $createdNodes);
			throw $e;
		}
	}

	private function processFileData(array $fileData, ?IUser $userManager, array $settings): Node {
		$name = $this->requireFileName($fileData);

		if (isset($fileData['uploadedFile'])) {
			$sourceNode = $this->fileService->getNodeFromData([
				'userManager' => $userManager,
				'name' => $name,
				'uploadedFile' => $fileData['uploadedFile'],
				'settings' => $settings,
			]);
		} else {
			$sourceNode = $this->fileService->getNodeFromData([
				'userManager' => $userManager,
				'name' => $name,
				'file' => $fileData,
				'settings' => $settings,
			]);
		}

		if (isset($settings['envelopeFolderId'])) {
			return $this->envelopeFileRelocator->ensureFileInEnvelopeFolder(
				$sourceNode,
				$settings['envelopeFolderId'],
				$userManager,
			);
		}

		return $sourceNode;
	}

	private function requireFileName(array $fileData): string {
		$name = trim((string)($fileData['name'] ?? ''));
		if ($name === '') {
			throw new LibresignException($this->l10n->t('File name is required'));
		}
		return $name;
	}

	private function rollbackEnvelopeCreation(?FileEntity $envelope, array $files, array $createdNodes): void {
		$this->rollbackCreatedNodes($createdNodes);
		$this->rollbackCreatedFiles($files);
		$this->rollbackEnvelope($envelope);
	}

	private function rollbackCreatedNodes(array $nodes): void {
		foreach ($nodes as $node) {
			try {
				$node->delete();
			} catch (\Throwable $deleteError) {
				$this->logger->error('Failed to rollback created node in envelope', [
					'nodeId' => $node->getId(),
					'error' => $deleteError->getMessage(),
				]);
			}
		}
	}

	private function rollbackCreatedFiles(array $files): void {
		foreach ($files as $file) {
			try {
				$this->fileMapper->delete($file);
			} catch (\Throwable $deleteError) {
				$this->logger->error('Failed to rollback created file entity in envelope', [
					'fileId' => $file->getId(),
					'error' => $deleteError->getMessage(),
				]);
			}
		}
	}

	private function rollbackEnvelope(?FileEntity $envelope): void {
		if ($envelope === null) {
			return;
		}

		try {
			$this->fileMapper->delete($envelope);
		} catch (\Throwable $deleteError) {
			$this->logger->error('Failed to rollback created envelope', [
				'envelopeId' => $envelope->getId(),
				'error' => $deleteError->getMessage(),
			]);
		}
	}

	private function createFileForEnvelope(array $fileData, ?IUser $userManager, array $settings): FileEntity {
		if (!isset($fileData['node'])) {
			throw new \InvalidArgumentException('Node not provided in file data');
		}

		$node = $fileData['node'];
		$fileName = $fileData['name'] ?? $node->getName();

		return $this->saveFile([
			'file' => ['fileNode' => $node],
			'name' => $fileName,
			'userManager' => $userManager,
			'status' => FileStatus::DRAFT->value,
			'settings' => $settings,
		]);
	}

	/**
	 * Save file data
	 *
	 * @param array{?userManager: IUser, ?signRequest: SignRequest, name: string, callback: string, uuid?: ?string, status: int, file?: array{fileId?: int, fileNode?: Node}} $data
	 */
	public function saveFile(array $data): FileEntity {
		if (!empty($data['uuid'])) {
			$file = $this->fileMapper->getByUuid($data['uuid']);
			$this->updateSignatureFlowIfAllowed($file, $data);
			if (!empty($data['name'])) {
				$file->setName($data['name']);
				$this->fileService->update($file);
			}
			return $this->fileStatusService->updateFileStatusIfUpgrade($file, $data['status'] ?? 0);
		}
		$fileId = null;
		if (isset($data['file']['fileNode']) && $data['file']['fileNode'] instanceof Node) {
			$fileId = $data['file']['fileNode']->getId();
		} elseif (!empty($data['file']['fileId'])) {
			$fileId = $data['file']['fileId'];
		}
		if (!is_null($fileId)) {
			try {
				$file = $this->fileMapper->getByNodeId($fileId);
				$this->updateSignatureFlowIfAllowed($file, $data);
				return $this->fileStatusService->updateFileStatusIfUpgrade($file, $data['status'] ?? 0);
			} catch (\Throwable) {
			}
		}

		$node = $this->fileService->getNodeFromData($data);

		$file = new FileEntity();
		$file->setNodeId($node->getId());
		if (isset($data['userManager']) && $data['userManager'] instanceof IUser) {
			$file->setUserId($data['userManager']->getUID());
		} elseif (isset($data['signRequest']) && $data['signRequest'] instanceof SignRequestEntity) {
			$signRequestFileId = $data['signRequest']->getFileId();
			if ($signRequestFileId) {
				$signRequestFile = $this->fileMapper->getById($signRequestFileId);
				$file->setUserId($signRequestFile->getUserId());
			}
		}
		$file->setUuid(UUIDUtil::getUUID());
		$file->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		$metadata = $this->getFileMetadata($node);
		$name = trim((string)($data['name'] ?? ''));
		if ($name === '') {
			$name = $node->getName();
		}
		$file->setName($this->removeExtensionFromName($name, $metadata));
		$file->setMetadata($metadata);
		if (!empty($data['callback'])) {
			$file->setCallback($data['callback']);
		}
		if (isset($data['status'])) {
			$file->setStatus($data['status']);
		} else {
			$file->setStatus(FileStatus::ABLE_TO_SIGN->value);
		}

		if (isset($data['parentFileId'])) {
			$file->setParentFileId($data['parentFileId']);
		}

		$this->setSignatureFlow($file, $data);
		$this->setDocMdpLevelFromGlobalConfig($file);

		$this->fileMapper->insert($file);
		return $file;
	}

	private function updateSignatureFlowIfAllowed(FileEntity $file, array $data): void {
		$adminFlow = $this->appConfig->getValueString(Application::APP_ID, 'signature_flow', SignatureFlow::NONE->value);
		$adminForcedConfig = $adminFlow !== SignatureFlow::NONE->value;

		if ($adminForcedConfig) {
			$adminFlowEnum = SignatureFlow::from($adminFlow);
			if ($file->getSignatureFlowEnum() !== $adminFlowEnum) {
				$file->setSignatureFlowEnum($adminFlowEnum);
				$this->fileService->update($file);
			}
			return;
		}

		if (isset($data['signatureFlow']) && !empty($data['signatureFlow'])) {
			$newFlow = SignatureFlow::from($data['signatureFlow']);
			if ($file->getSignatureFlowEnum() !== $newFlow) {
				$file->setSignatureFlowEnum($newFlow);
				$this->fileService->update($file);
			}
		}
	}

	private function setSignatureFlow(FileEntity $file, array $data): void {
		$adminFlow = $this->appConfig->getValueString(Application::APP_ID, 'signature_flow', SignatureFlow::NONE->value);

		if (isset($data['signatureFlow']) && !empty($data['signatureFlow'])) {
			$file->setSignatureFlowEnum(SignatureFlow::from($data['signatureFlow']));
		} elseif ($adminFlow !== SignatureFlow::NONE->value) {
			$file->setSignatureFlowEnum(SignatureFlow::from($adminFlow));
		} else {
			$file->setSignatureFlowEnum(SignatureFlow::NONE);
		}
	}

	private function setDocMdpLevelFromGlobalConfig(FileEntity $file): void {
		if ($this->docMdpConfigService->isEnabled()) {
			$docmdpLevel = $this->docMdpConfigService->getLevel();
			$file->setDocmdpLevelEnum($docmdpLevel);
		}
	}

	private function getFileMetadata(\OCP\Files\Node $node): array {
		$metadata = [];
		if ($extension = strtolower($node->getExtension())) {
			$metadata = [
				'extension' => $extension,
			];
			if ($metadata['extension'] === 'pdf') {
				$this->pdfMetadataExtractor->setFile($node);
				$metadata = array_merge(
					$metadata,
					$this->pdfMetadataExtractor->getPageDimensions()
				);
				$metadata['pdfVersion'] = $this->pdfMetadataExtractor->getPdfVersion();
			}
		}
		return $metadata;
	}

	private function removeExtensionFromName(string $name, array $metadata): string {
		if (!isset($metadata['extension'])) {
			return $name;
		}
		$extensionPattern = '/\.' . preg_quote($metadata['extension'], '/') . '$/i';
		$result = preg_replace($extensionPattern, '', $name);
		return $result ?? $name;
	}

	private function deleteIdentifyMethodIfNotExits(array $signers, FileEntity $file): void {
		$signRequests = $this->signRequestMapper->getByFileId($file->getId());
		foreach ($signRequests as $key => $signRequest) {
			$identifyMethods = $this->identifyMethod->getIdentifyMethodsFromSignRequestId($signRequest->getId());
			if (empty($identifyMethods)) {
				$this->unassociateToUser($file->getId(), $signRequest->getId());
				continue;
			}
			foreach ($identifyMethods as $methodName => $list) {
				foreach ($list as $method) {
					$exists[$key]['identify'][$methodName] = $method->getEntity()->getIdentifierValue();
					if (!$this->identifyMethodExists($signers, $method)) {
						$this->unassociateToUser($file->getId(), $signRequest->getId());
						continue 3;
					}
				}
			}
		}
	}

	private function identifyMethodExists(array $signers, IIdentifyMethod $identifyMethod): bool {
		foreach ($signers as $signer) {
			if (!empty($signer['identifyMethods'])) {
				foreach ($signer['identifyMethods'] as $data) {
					if ($identifyMethod->getEntity()->getIdentifierKey() !== $data['method']) {
						continue;
					}
					if ($identifyMethod->getEntity()->getIdentifierValue() === $data['value']) {
						return true;
					}
				}
			} else {
				foreach ($signer['identify'] as $method => $value) {
					if ($identifyMethod->getEntity()->getIdentifierKey() !== $method) {
						continue;
					}
					if ($identifyMethod->getEntity()->getIdentifierValue() === $value) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * @return SignRequestEntity[]
	 *
	 * @psalm-return list<SignRequestEntity>
	 */
	private function associateToSigners(array $data, FileEntity $file): array {
		$return = [];
		if (!empty($data['signers'])) {
			$this->deleteIdentifyMethodIfNotExits($data['signers'], $file);
			$this->identifyMethod->clearCache();

			$this->sequentialSigningService->resetOrderCounter();
			$fileStatus = $data['status'] ?? null;

			foreach ($data['signers'] as $signer) {
				$userProvidedOrder = isset($signer['signingOrder']) ? (int)$signer['signingOrder'] : null;
				$signingOrder = $this->sequentialSigningService->determineSigningOrder($userProvidedOrder);
				$signerStatus = $signer['status'] ?? null;
				$shouldNotify = !isset($signer['notify']) || $signer['notify'] !== 0;

				if (isset($signer['identifyMethods'])) {
					foreach ($signer['identifyMethods'] as $identifyMethod) {
						$return[] = $this->signRequestService->createOrUpdateSignRequest(
							identifyMethods: [
								$identifyMethod['method'] => $identifyMethod['value'],
							],
							displayName: $signer['displayName'] ?? '',
							description: $signer['description'] ?? '',
							notify: $shouldNotify,
							fileId: $file->getId(),
							signingOrder: $signingOrder,
							fileStatus: $fileStatus,
							signerStatus: $signerStatus,
						);
					}
				} else {
					$return[] = $this->signRequestService->createOrUpdateSignRequest(
						identifyMethods: $signer['identify'],
						displayName: $signer['displayName'] ?? '',
						description: $signer['description'] ?? '',
						notify: $shouldNotify,
						fileId: $file->getId(),
						signingOrder: $signingOrder,
						fileStatus: $fileStatus,
						signerStatus: $signerStatus,
					);
				}
			}
		}
		return $return;
	}



	private function saveVisibleElements(array $data, FileEntity $file): array {
		if (empty($data['visibleElements'])) {
			return [];
		}
		$persisted = [];
		foreach ($data['visibleElements'] as $element) {
			if ($file->isEnvelope() && !empty($element['signRequestId'])) {
				$envelopeSignRequest = $this->signRequestMapper->getById((int)$element['signRequestId']);
				// Only translate if the provided SR belongs to the envelope itself
				if ($envelopeSignRequest && $envelopeSignRequest->getFileId() === $file->getId()) {
					$childrenSrs = $this->signRequestMapper->getByEnvelopeChildrenAndIdentifyMethod($file->getId(), (int)$element['signRequestId']);
					foreach ($childrenSrs as $childSr) {
						if ($childSr->getFileId() === (int)$element['fileId']) {
							$element['signRequestId'] = $childSr->getId();
							break;
						}
					}
				}
			}

			$persisted[] = $this->fileElementService->saveVisibleElement($element);
		}
		return $persisted;
	}


	public function validateNewRequestToFile(array $data): void {
		$this->validateNewFile($data);
		$this->validateSigners($data);
		$this->validateHelper->validateFileStatus($data);
	}

	public function validateNewFile(array $data): void {
		if (empty($data['name'])) {
			throw new \Exception($this->l10n->t('File name is required'));
		}
		$this->validateHelper->validateNewFile($data);
	}

	public function validateSigners(array $data): void {
		if (empty($data['signers'])) {
			if (($data['status'] ?? FileStatus::ABLE_TO_SIGN->value) === FileStatus::DRAFT->value) {
				return;
			}
			throw new \Exception($this->l10n->t('Empty signers list'));
		}
		if (!is_array($data['signers'])) {
			// TRANSLATION This message will be displayed when the request to API with the key signers has a value that is not an array
			throw new \Exception($this->l10n->t('Signers list needs to be an array'));
		}
		foreach ($data['signers'] as $signer) {
			if (!array_key_exists('identify', $signer)) {
				throw new \Exception('Identify key not found');
			}
			$this->identifyMethod->setAllEntityData($signer);
		}
	}



	public function unassociateToUser(int $fileId, int $signRequestId): void {
		$file = $this->fileMapper->getById($fileId);
		$signRequest = $this->signRequestMapper->getByFileIdAndSignRequestId($fileId, $signRequestId);
		$deletedOrder = $signRequest->getSigningOrder();
		$groupedIdentifyMethods = $this->identifyMethod->getIdentifyMethodsFromSignRequestId($signRequestId);

		$this->dispatchCancellationEventIfNeeded($signRequest, $file, $groupedIdentifyMethods);

		try {
			$this->signRequestMapper->delete($signRequest);
			$this->identifyMethod->deleteBySignRequestId($signRequestId);
			$visibleElements = $this->fileElementMapper->getByFileIdAndSignRequestId($fileId, $signRequestId);
			foreach ($visibleElements as $visibleElement) {
				$this->fileElementMapper->delete($visibleElement);
			}

			$this->sequentialSigningService
				->setFile($file)
				->reorderAfterDeletion($file->getId(), $deletedOrder);

			$this->propagateSignerDeletionToChildren($file, $signRequest);
			$this->revertStatusToDraftIfNoSignersRemain($file);
		} catch (\Throwable) {
		}
	}

	private function revertStatusToDraftIfNoSignersRemain(FileEntity $file): void {
		$remaining = $this->signRequestMapper->getByFileId($file->getId());
		if (empty($remaining)) {
			$file->setStatus(FileStatus::DRAFT->value);
			$this->fileStatusService->update($file);
		}
	}

	private function propagateSignerDeletionToChildren(FileEntity $envelope, SignRequestEntity $deletedSignRequest): void {
		if ($envelope->getNodeType() !== 'envelope') {
			return;
		}

		$children = $this->fileMapper->getChildrenFiles($envelope->getId());

		$identifyMethods = $this->identifyMethod->getIdentifyMethodsFromSignRequestId($deletedSignRequest->getId());
		if (empty($identifyMethods)) {
			return;
		}

		foreach ($children as $child) {
			try {
				$this->identifyMethod->clearCache();
				$childSignRequest = $this->signRequestService->getSignRequestByIdentifyMethod(
					current(reset($identifyMethods)),
					$child->getId()
				);

				if ($childSignRequest->getId()) {
					$this->unassociateToUser($child->getId(), $childSignRequest->getId());
				}
			} catch (\Throwable $e) {
				continue;
			}
		}
	}

	private function dispatchCancellationEventIfNeeded(
		SignRequestEntity $signRequest,
		FileEntity $file,
		array $groupedIdentifyMethods,
	): void {
		if ($signRequest->getStatus() !== \OCA\Libresign\Enum\SignRequestStatus::ABLE_TO_SIGN->value) {
			return;
		}

		try {
			foreach ($groupedIdentifyMethods as $identifyMethods) {
				foreach ($identifyMethods as $identifyMethod) {
					$event = new SignRequestCanceledEvent(
						$signRequest,
						$file,
						$identifyMethod,
					);
					$this->eventDispatcher->dispatchTyped($event);
				}
			}
		} catch (\Throwable $e) {
			$this->logger->error('Error dispatching SignRequestCanceledEvent: ' . $e->getMessage(), ['exception' => $e]);
		}
	}

	public function deleteRequestSignature(array $data): void {
		if (!empty($data['uuid'])) {
			$signatures = $this->signRequestMapper->getByFileUuid($data['uuid']);
			$fileData = $this->fileMapper->getByUuid($data['uuid']);
		} elseif (!empty($data['file']['fileId'])) {
			$fileData = $this->fileMapper->getById($data['file']['fileId']);
			$signatures = $this->signRequestMapper->getByFileId($fileData->getId());
		} else {
			throw new \Exception($this->l10n->t('Please provide either UUID or File object'));
		}
		foreach ($signatures as $signRequest) {
			$this->identifyMethod->deleteBySignRequestId($signRequest->getId());
			$this->signRequestMapper->delete($signRequest);
		}
		$this->fileMapper->delete($fileData);
		$this->fileElementService->deleteVisibleElements($fileData->getId());
	}
}
