<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Enum\FileStatus;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class NodeCleanupService {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * Cleanup all LibreSign records for a given node id.
	 */
	public function deleteAllByNodeId(int $nodeId): void {
		if ($this->cleanupSignedFilesByNodeId($nodeId) > 0) {
			return;
		}

		$fullOuterJoin = $this->db->getQueryBuilder();
		$fullOuterJoin->select($fullOuterJoin->expr()->literal(1));

		$qb = $this->db->getQueryBuilder();
		$qb
			->selectAlias('current.id', 'file_id')
			->selectAlias('current.metadata', 'file_metadata')
			->selectAlias('current.signed_node_id', 'current_signed_node_id')
			->selectAlias('parent.id', 'parent_id')
			->selectAlias('children.id', 'child_id')
			->selectAlias('ue.id', 'user_element_id')
			->selectAlias('fe.file_id', 'file_element_file_id')
			->from($qb->createFunction('(' . $fullOuterJoin->getSQL() . ')'), 'foj')
			->leftJoin('foj', 'libresign_file', 'current', $qb->expr()->eq('current.node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->leftJoin('current', 'libresign_file', 'parent', $qb->expr()->eq('parent.id', 'current.parent_file_id'))
			->leftJoin('current', 'libresign_file', 'children', $qb->expr()->eq('children.parent_file_id', 'current.id'))
			->leftJoin('foj', 'libresign_user_element', 'ue', $qb->expr()->eq('ue.node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->leftJoin('foj', 'libresign_file_element', 'fe', $qb->expr()->eq('fe.file_id', 'current.id'));

		$cursor = $qb->executeQuery();

		$deletedFiles = [];
		$deletedUserElements = false;
		$deletedFileElements = [];

		while ($row = $cursor->fetchAssociative()) {
			if (!empty($row['user_element_id']) && !$deletedUserElements) {
				$deletedUserElements = true;
				$this->deleteUserElementByNodeId($nodeId);
			}

			if (!empty($row['file_element_file_id']) && !isset($deletedFileElements[$row['file_element_file_id']])) {
				$deletedFileElements[(int)$row['file_element_file_id']] = true;
				$this->deleteFileElementByFileId((int)$row['file_element_file_id']);
			}

			if (!empty($row['file_id']) && !isset($deletedFiles[$row['file_id']])) {
				$deletedFiles[(int)$row['file_id']] = true;

				if (!empty($row['parent_id']) || !empty($row['child_id'])) {
					$this->deleteSigningData((int)$row['file_id']);
					$this->deleteFileById((int)$row['file_id']);
				} else {
					$this->markOriginalFileAsDeleted((int)$row['file_id'], isset($row['file_metadata']) ? (string)$row['file_metadata'] : null);
				}
			}
		}
		$cursor->closeCursor();
	}

	/**
	 * Cleanup files where signed_node_id matches the given node id.
	 *
	 * @return int number of records cleaned
	 */
	public function cleanupSignedFilesByNodeId(int $nodeId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'node_id', 'signed_node_id')
			->from('libresign_file')
			->where($qb->expr()->eq('signed_node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));

		$files = $qb->executeQuery()->fetchAllAssociative();
		foreach ($files as $file) {
			$fileId = (int)$file['id'];
			$this->deleteSigningData($fileId);
			$this->detachSignedFile($fileId);
		}

		return count($files);
	}

	/**
	 * Delete user elements by node_id.
	 */
	public function deleteUserElementByNodeId(int $nodeId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('libresign_user_element')
			->where($qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function markOriginalFileAsDeleted(int $fileId, ?string $metadataJson = null): void {
		$existingMetadata = [];

		if ($metadataJson !== null && $metadataJson !== '') {
			try {
				$decoded = json_decode($metadataJson, true, 512, JSON_THROW_ON_ERROR);
				if (is_array($decoded)) {
					$existingMetadata = $decoded;
				}
			} catch (\Throwable) {
			}
		}

		$existingMetadata['original_file_deleted'] = true;
		$existingMetadata['original_file_deleted_at'] = (new \DateTime('now', new \DateTimeZone('UTC')))->format(\DateTime::ATOM);

		$update = $this->db->getQueryBuilder();
		$update->update('libresign_file')
			->set('node_id', $update->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->set('metadata', $update->createNamedParameter(json_encode($existingMetadata), IQueryBuilder::PARAM_STR))
			->where($update->expr()->eq('id', $update->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function detachSignedFile(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('libresign_file')
			->set('signed_node_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->set('status', $qb->createNamedParameter(FileStatus::DRAFT->value, IQueryBuilder::PARAM_INT))
			->set('signed_hash', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function deleteFileElementByFileId(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('libresign_file_element')
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function deleteSigningData(int $fileId): void {
		$this->deleteIdentifyMethods($fileId);
		$this->deleteSignRequests($fileId);
		$this->deleteIdDocs($fileId);
		$this->deleteFileElementByFileId($fileId);
	}

	private function deleteIdentifyMethods(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('libresign_sign_request')
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		$cursor = $qb->executeQuery();

		while ($row = $cursor->fetchAssociative()) {
			$delete = $this->db->getQueryBuilder();
			$delete->delete('libresign_identify_method')
				->where($delete->expr()->eq('sign_request_id', $delete->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT)))
				->executeStatement();
		}
		$cursor->closeCursor();
	}

	private function deleteSignRequests(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('libresign_sign_request')
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function deleteIdDocs(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('libresign_id_docs')
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function deleteFileById(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('libresign_file')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
