<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Envelope;

use DateTime;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\FolderService;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Envelope\EnvelopePolicy;
use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicy;
use OCA\Libresign\Service\Policy\Provider\ObserverProfile\ObserverProfilePolicyValue;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use Sabre\DAV\UUIDUtil;

class EnvelopeService {
	public function __construct(
		protected FileMapper $fileMapper,
		protected IL10N $l10n,
		protected PolicyService $policyService,
		protected IAppConfig $appConfig,
		protected FolderService $folderService,
	) {
	}

	public function isEnabled(): bool {
		return $this->policyService->resolve(EnvelopePolicy::KEY)->getEffectiveValueAsBool(true);
	}

	/**
	 * @throws LibresignException
	 */
	public function validateEnvelopeConstraints(int $fileCount): void {
		if (!$this->isEnabled()) {
			// TRANSLATORS Error shown when the multi-document envelope feature is disabled by the administrator.
			throw new LibresignException($this->l10n->t('Envelope feature is disabled'));
		}

		$maxFiles = $this->getMaxFilesPerEnvelope();
		if ($fileCount > $maxFiles) {
			throw new LibresignException(
				// TRANSLATORS Error shown when adding documents would exceed the configured envelope size limit. %s is the maximum number of files allowed.
				$this->l10n->t('Maximum number of files per envelope (%s) exceeded', [$maxFiles])
			);
		}
	}

	/**
	 * @param array{
	 *     userManager?: IUser,
	 *     policyOverrides?: array<string, mixed>,
	 *     policyActiveContext?: array<string, mixed>|null
	 * } $policyData
	 */
	public function createEnvelope(
		string $name,
		string $userId,
		int $filesCount = 0,
		?string $path = null,
		array $policyData = [],
	): FileEntity {
		$this->folderService->setUserId($userId);

		$uuid = UUIDUtil::getUUID();
		if ($path) {
			$envelopeFolder = $this->folderService->getOrCreateFolderByAbsolutePath($path);
		} else {
			$parentFolder = $this->folderService->getFolder();
			$folderName = $name . '_' . $uuid;
			$envelopeFolder = $parentFolder->newFolder($folderName);
		}

		$envelope = new FileEntity();
		$envelope->setNodeId($envelopeFolder->getId());
		$envelope->setNodeTypeEnum(NodeType::ENVELOPE);
		$envelope->setName($name);
		$envelope->setUuid($uuid);
		$envelope->setCreatedAt(new DateTime());
		$envelope->setStatusEnum(FileStatus::DRAFT);

		$envelope->setMetadata(['filesCount' => $filesCount]);
		$this->storeObserverProfilePolicySnapshot($envelope, $policyData);

		if ($userId) {
			$envelope->setUserId($userId);
		}

		return $this->fileMapper->insert($envelope);
	}

	public function addFileToEnvelope(int $envelopeId, FileEntity $file): FileEntity {
		$envelope = $this->fileMapper->getById($envelopeId);

		if (!$envelope->isEnvelope()) {
			// TRANSLATORS Error shown when the given identifier does not refer to a signature envelope.
			throw new LibresignException($this->l10n->t('The specified ID is not an envelope'));
		}

		if ($envelope->getStatus() > FileStatus::DRAFT->value) {
			// TRANSLATORS Error shown when trying to add documents to an envelope that already started the signing process.
			throw new LibresignException($this->l10n->t('Cannot add files to an envelope that is already in signing process'));
		}

		$maxFiles = $this->getMaxFilesPerEnvelope();
		$currentCount = $this->fileMapper->countChildrenFiles($envelopeId);
		if ($currentCount >= $maxFiles) {
			throw new LibresignException(
				// TRANSLATORS Error shown when adding documents would exceed the configured envelope size limit. %s is the maximum number of files allowed.
				$this->l10n->t('Maximum number of files per envelope (%s) exceeded', [$maxFiles])
			);
		}

		$file->setParentFileId($envelopeId);
		$file->setNodeTypeEnum(NodeType::FILE);

		return $this->fileMapper->update($file);
	}

	public function getEnvelopeByFileId(int $fileId): ?FileEntity {
		try {
			return $this->fileMapper->getParentEnvelope($fileId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function getEnvelopeFolder(FileEntity $envelope): \OCP\Files\Folder {
		$userId = $envelope->getUserId();
		if (!$userId) {
			throw new LibresignException('Envelope does not have a user');
		}

		$this->folderService->setUserId($userId);
		$userRootFolder = $this->folderService->getUserRootFolder();

		$envelopeFolderNode = $userRootFolder->getFirstNodeById($envelope->getNodeId());
		if (!$envelopeFolderNode instanceof \OCP\Files\Folder) {
			throw new LibresignException('Envelope folder not found');
		}

		return $envelopeFolderNode;
	}

	private function getMaxFilesPerEnvelope(): int {
		return $this->appConfig->getValueInt(Application::APP_ID, 'envelope_max_files', 50);
	}

	/**
	 * @param array{
	 *     userManager?: IUser,
	 *     policyOverrides?: array<string, mixed>,
	 *     policyActiveContext?: array<string, mixed>|null
	 * } $policyData
	 */
	private function storeObserverProfilePolicySnapshot(FileEntity $envelope, array $policyData): void {
		$user = ($policyData['userManager'] ?? null) instanceof IUser ? $policyData['userManager'] : null;
		$requestOverrides = [];
		if (isset($policyData['policyOverrides']) && is_array($policyData['policyOverrides'])
			&& array_key_exists(ObserverProfilePolicy::KEY, $policyData['policyOverrides'])
		) {
			$requestOverrides[ObserverProfilePolicy::KEY] = ObserverProfilePolicyValue::normalize(
				$policyData['policyOverrides'][ObserverProfilePolicy::KEY],
			);
		}

		$activeContext = $this->extractPolicyActiveContext($policyData);
		$resolvedPolicy = $activeContext === null
			? $this->policyService->resolveForUser(ObserverProfilePolicy::KEY, $user, $requestOverrides)
			: $this->policyService->resolveForUser(ObserverProfilePolicy::KEY, $user, $requestOverrides, $activeContext);

		$metadata = $envelope->getMetadata() ?? [];
		$policySnapshot = $metadata['policy_snapshot'] ?? [];
		$policySnapshot[ObserverProfilePolicy::KEY] = [
			'effectiveValue' => ObserverProfilePolicyValue::normalize($resolvedPolicy->getEffectiveValue()),
			'sourceScope' => $resolvedPolicy->getSourceScope(),
		];
		$metadata['policy_snapshot'] = $policySnapshot;
		$envelope->setMetadata($metadata);
	}

	/**
	 * @param array{policyActiveContext?: array<string, mixed>|null} $policyData
	 * @return array{type: string, id: string}|null
	 */
	private function extractPolicyActiveContext(array $policyData): ?array {
		if (!isset($policyData['policyActiveContext']) || !is_array($policyData['policyActiveContext'])) {
			return null;
		}

		$type = $policyData['policyActiveContext']['type'] ?? null;
		$id = $policyData['policyActiveContext']['id'] ?? null;
		if (!is_string($type) || !is_string($id) || $type === '' || $id === '') {
			return null;
		}

		return [
			'type' => $type,
			'id' => $id,
		];
	}
}
