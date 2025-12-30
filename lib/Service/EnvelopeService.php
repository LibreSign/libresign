<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use DateTime;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Exception\LibresignException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\IL10N;
use Sabre\DAV\UUIDUtil;

class EnvelopeService {
	public function __construct(
		protected FileMapper $fileMapper,
		protected IL10N $l10n,
		protected IAppConfig $appConfig,
		protected FolderService $folderService,
	) {
	}

	public function isEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, 'envelope_enabled', true);
	}

	/**
	 * @throws LibresignException
	 */
	public function validateEnvelopeConstraints(int $fileCount): void {
		if (!$this->isEnabled()) {
			throw new LibresignException($this->l10n->t('Envelope feature is disabled'));
		}

		$maxFiles = $this->getMaxFilesPerEnvelope();
		if ($fileCount > $maxFiles) {
			throw new LibresignException(
				$this->l10n->t('Maximum number of files per envelope (%s) exceeded', [$maxFiles])
			);
		}
	}

	public function createEnvelope(string $name, string $userId, int $filesCount = 0): FileEntity {
		$this->folderService->setUserId($userId);

		$parentFolder = $this->folderService->getFolder();

		$uuid = UUIDUtil::getUUID();
		$folderName = $name . '_' . $uuid;
		$envelopeFolder = $parentFolder->newFolder($folderName);

		$envelope = new FileEntity();
		$envelope->setNodeId($envelopeFolder->getId());
		$envelope->setNodeTypeEnum(NodeType::ENVELOPE);
		$envelope->setName($name);
		$envelope->setUuid($uuid);
		$envelope->setCreatedAt(new DateTime());
		$envelope->setStatus(FileEntity::STATUS_DRAFT);

		$envelope->setMetadata(['filesCount' => $filesCount]);

		if ($userId) {
			$envelope->setUserId($userId);
		}

		return $this->fileMapper->insert($envelope);
	}

	public function addFileToEnvelope(int $envelopeId, FileEntity $file): FileEntity {
		$envelope = $this->fileMapper->getById($envelopeId);

		if (!$envelope->isEnvelope()) {
			throw new LibresignException($this->l10n->t('The specified ID is not an envelope'));
		}

		if ($envelope->getStatus() > FileEntity::STATUS_DRAFT) {
			throw new LibresignException($this->l10n->t('Cannot add files to an envelope that is already in signing process'));
		}

		$maxFiles = $this->getMaxFilesPerEnvelope();
		$currentCount = $this->fileMapper->countChildrenFiles($envelopeId);
		if ($currentCount >= $maxFiles) {
			throw new LibresignException(
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

		$this->folderService->setUserId($userId);
		$userFolder = $this->folderService->getFolder();

		$envelopeFolderNode = $userFolder->getFirstNodeById($envelope->getNodeId());

		return $envelopeFolderNode;
	}

	private function getMaxFilesPerEnvelope(): int {
		return $this->appConfig->getValueInt(Application::APP_ID, 'envelope_max_files', 50);
	}
}
