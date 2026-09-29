<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\FileStatus;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\File;
use OCP\Files\NotFoundException;

class AccountFileService {
	public function __construct(
		private SignRequestMapper $signRequestMapper,
		private FileMapper $fileMapper,
		private FolderService $folderService,
	) {
	}

	public function getSignRequestByUuid(string $uuid): SignRequest {
		return $this->signRequestMapper->getByUuid($uuid);
	}

	/** Resolve a sign-request UUID to its database file and readable node. */
	public function getFileByUuid(string $uuid): array {
		$signRequest = $this->getSignRequestByUuid($uuid);
		$fileData = $this->fileMapper->getById($signRequest->getFileId());
		$fileToSign = $this->folderService->getReadableNodeById($fileData->getUserId(), $fileData->getNodeId());
		return [
			'fileData' => $fileData,
			'fileToSign' => $fileToSign instanceof File ? $fileToSign : null,
		];
	}

	/**
	 * Resolve a document UUID to its signed or original PDF node.
	 *
	 * @throws DoesNotExistException When the document or its file node cannot be found.
	 */
	public function getPdfByUuid(string $uuid): File {
		$fileData = $this->fileMapper->getByUuid($uuid);

		if (in_array($fileData->getStatus(), [FileStatus::PARTIAL_SIGNED->value, FileStatus::SIGNED->value])) {
			$nodeId = $fileData->getSignedNodeId();
		} else {
			$nodeId = $fileData->getNodeId();
		}
		if ($nodeId === null) {
			throw new DoesNotExistException('Not found');
		}

		$userId = $this->fileMapper->getStorageUserIdByUuid($uuid);
		$this->folderService->setUserId($userId);
		try {
			return $this->folderService->getFileByNodeId($nodeId);
		} catch (NotFoundException) {
			throw new DoesNotExistException('Not found');
		}
	}

	/**
	 * @throws DoesNotExistException When the file node cannot be found.
	 */
	public function getFileByNodeId(int $nodeId): File {
		try {
			return $this->folderService->getFileByNodeId($nodeId);
		} catch (NotFoundException) {
			throw new DoesNotExistException('Not found');
		}
	}
}
