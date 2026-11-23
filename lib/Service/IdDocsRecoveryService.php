<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\IdDocsMapper;
use OCP\AppFramework\Db\TTransactional;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Recovers identification documents that external signers uploaded before
 * the storage fix of #8368: the node was written to the unauthenticated
 * appdata folder while the file record points to the signed document owner.
 *
 * @psalm-type RecoveryResult = array{idDocId: int, fileId: int, nodeId: ?int, status: string, reason: string, newNodeId?: int, leftoverNodeId?: int}
 */
class IdDocsRecoveryService {
	use TTransactional;

	public const STATUS_AFFECTED = 'affected';
	public const STATUS_ALREADY_CORRECT = 'already_correct';
	public const STATUS_AMBIGUOUS = 'ambiguous';
	public const STATUS_UNRECOVERABLE = 'unrecoverable';
	public const STATUS_RECOVERED = 'recovered';
	public const STATUS_FAILED = 'failed';

	public function __construct(
		private IdDocsMapper $idDocsMapper,
		private FileMapper $fileMapper,
		private FolderService $folderService,
		private IUserManager $userManager,
		private IDBConnection $db,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Classify every external-signer identification document and, when
	 * $execute is true, copy each affected one into its owner's storage.
	 *
	 * @return list<RecoveryResult>
	 */
	public function run(bool $execute): array {
		$results = [];
		foreach ($this->idDocsMapper->getExternalSignerDocuments() as $row) {
			[$result, $owner, $source] = $this->classify($row);
			if ($execute && $result['status'] === self::STATUS_AFFECTED && $owner instanceof IUser && $source instanceof File) {
				$result = $this->recover($result, $owner, $source);
			}
			$results[] = $result;
		}
		return $results;
	}

	/**
	 * @param array{id_doc_id: int, file_id: int, sign_request_id: ?int, file_exists: bool, node_id: ?int, signed_node_id: ?int, owner: ?string, signed_document_id: ?int, signed_document_owner: ?string, signed_document_exists: bool} $row
	 * @return array{0: RecoveryResult, 1: ?IUser, 2: ?File}
	 */
	private function classify(array $row): array {
		$result = [
			'idDocId' => $row['id_doc_id'],
			'fileId' => $row['file_id'],
			'nodeId' => $row['node_id'],
		];
		$skip = static fn (string $status, string $reason): array => [$result + ['status' => $status, 'reason' => $reason], null, null];

		if (!$row['file_exists']) {
			return $skip(self::STATUS_UNRECOVERABLE, 'file record missing');
		}
		if ($row['node_id'] === null) {
			return $skip(self::STATUS_UNRECOVERABLE, 'file node missing');
		}
		if ($row['signed_node_id'] !== null) {
			return $skip(self::STATUS_AMBIGUOUS, 'document has a signed copy');
		}
		if ($row['owner'] === null) {
			return $skip(self::STATUS_UNRECOVERABLE, 'owner missing');
		}
		$owner = $this->userManager->get($row['owner']);
		if (!$owner instanceof IUser) {
			return $skip(self::STATUS_UNRECOVERABLE, 'owner account missing');
		}
		if ($row['sign_request_id'] === null) {
			return $skip(self::STATUS_UNRECOVERABLE, 'sign request missing');
		}
		if (!$row['signed_document_exists']) {
			return $skip(self::STATUS_UNRECOVERABLE, 'signed document missing');
		}
		if ($row['signed_document_owner'] !== $row['owner']) {
			return $skip(self::STATUS_AMBIGUOUS, 'owner differs from signed document owner');
		}
		if ($this->folderService->getReadableNodeById($owner->getUID(), $row['node_id']) instanceof File) {
			return $skip(self::STATUS_ALREADY_CORRECT, 'readable in owner storage');
		}
		if ($this->fileMapper->countByNodeId($row['node_id']) !== 1) {
			return $skip(self::STATUS_AMBIGUOUS, 'node referenced by several file records');
		}
		try {
			$source = $this->folderService->getUnauthenticatedFileByNodeId($row['node_id']);
		} catch (NotFoundException) {
			return $skip(self::STATUS_UNRECOVERABLE, 'source file missing');
		}
		return [$result + ['status' => self::STATUS_AFFECTED, 'reason' => 'stored in unauthenticated appdata'], $owner, $source];
	}

	/**
	 * Copy the source into a new folder of the owner, verify it and only then
	 * point the file record to the copy. The original is never removed.
	 *
	 * @param RecoveryResult $result
	 * @return RecoveryResult
	 */
	private function recover(array $result, IUser $owner, File $source): array {
		$folder = null;
		try {
			$folder = $this->folderService->getFolderForUserFile($owner, ['name' => 'recovered-id-doc-' . $result['fileId']]);
			// FolderService falls back to app data when the owner's files cannot
			// be written, which would only create another orphan.
			$ownerRoot = $this->folderService->getUserFolder($owner->getUID())->getPath();
			if (!str_starts_with($folder->getPath(), $ownerRoot . '/')) {
				return $this->fail($result, 'owner storage not available', $folder);
			}
			$stream = $source->fopen('r');
			if ($stream === false) {
				return $this->fail($result, 'source file not readable', $folder);
			}
			try {
				$copy = $folder->newFile($source->getName(), $stream);
			} finally {
				if (is_resource($stream)) {
					fclose($stream);
				}
			}
			$sourceHash = $source->hash('sha256');
			if (preg_match('/\A[0-9a-f]{64}\z/i', (string)$sourceHash) !== 1 || $sourceHash !== $copy->hash('sha256')) {
				return $this->fail($result, 'content verification failed', $folder);
			}
			$newNodeId = $copy->getId();
			if (!$this->folderService->getReadableNodeById($owner->getUID(), $newNodeId) instanceof File) {
				return $this->fail($result, 'copy not readable in owner storage', $folder);
			}
			$oldNodeId = (int)$result['nodeId'];
			$ownerId = $owner->getUID();
			$updated = $this->atomic(function () use ($result, $oldNodeId, $newNodeId, $ownerId): bool {
				$file = $this->fileMapper->getByIdForUpdate($result['fileId']);
				if ($file->getNodeId() !== $oldNodeId || $file->getSignedNodeId() !== null || $file->getUserId() !== $ownerId) {
					return false;
				}
				$file->setNodeId($newNodeId);
				$this->fileMapper->update($file);
				return true;
			}, $this->db);
			if (!$updated) {
				return $this->fail($result, 'file record changed during recovery', $folder);
			}
			return array_merge($result, [
				'status' => self::STATUS_RECOVERED,
				'reason' => 'copied to owner storage, original kept',
				'newNodeId' => $newNodeId,
			]);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to recover identification document', [
				'idDocId' => $result['idDocId'],
				'fileId' => $result['fileId'],
				'exception' => $e,
			]);
			return $this->fail($result, 'unexpected error, see the server log', $folder);
		}
	}

	/**
	 * Report a failure and remove the folder created for the copy, so a retry
	 * starts from the same state.
	 *
	 * @param RecoveryResult $result
	 * @return RecoveryResult
	 */
	private function fail(array $result, string $reason, ?Folder $folder): array {
		$result = array_merge($result, ['status' => self::STATUS_FAILED, 'reason' => $reason]);
		if ($folder === null) {
			return $result;
		}
		try {
			$folder->delete();
		} catch (\Throwable $e) {
			$this->logger->warning('Could not remove the copy of an identification document after a failed recovery', [
				'idDocId' => $result['idDocId'],
				'exception' => $e,
			]);
			$result['leftoverNodeId'] = $folder->getId();
		}
		return $result;
	}
}
