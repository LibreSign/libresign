<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Enum\NodeType;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\Comments\ICommentsManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\DB
 * @template-extends QBMapper<File>
 */
class FileMapper extends QBMapper {
	/** @var File[] */
	private $file = [];

	public function __construct(
		IDBConnection $db,
		private IL10N $l,
	) {
		parent::__construct($db, 'libresign_file');
	}

	public function flushCache(?int $fileId = null): void {
		if ($fileId !== null) {
			$this->file = array_filter($this->file, fn ($f) => $f->getId() !== $fileId);
		} else {
			$this->file = [];
		}
	}

	/**
	 * Return LibreSign file by ID
	 *
	 * @throws DoesNotExistException
	 * @return File Row of table libresign_file
	 */
	public function getById(int $id): File {
		foreach ($this->file as $file) {
			if ($file->getId() === $id) {
				return $file;
			}
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		/** @var File */
		$file = $this->findEntity($qb);

		$this->file = array_filter($this->file, fn ($f) => $f->getId() !== $id);
		$this->file[] = $file;

		return $file;
	}

	/**
	 * Return LibreSign file by signed hash
	 *
	 * @throws DoesNotExistException
	 * @return File Row of table libresign_file
	 */
	public function getBySignedHash(string $hash): File {
		foreach ($this->file as $file) {
			if ($file->getSignedHash() === $hash) {
				return $file;
			}
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('f.*')
			->from($this->getTableName(), 'f')
			->join('f', 'libresign_sign_request', 'sr', $qb->expr()->eq('f.id', 'sr.file_id'))
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('f.signed_hash', $qb->createNamedParameter($hash)),
					$qb->expr()->eq('sr.signed_hash', $qb->createNamedParameter($hash))
				)
			)
			->setMaxResults(1);

		/** @var File */
		$file = $this->findEntity($qb);
		$this->file[] = $file;
		return $file;
	}

	/**
	 * Return LibreSign file by file UUID
	 */
	public function getByUuid(?string $uuid = null): File {
		if (is_null($uuid) && !empty($this->file)) {
			return current($this->file);
		}
		foreach ($this->file as $file) {
			if ($file->getUuid() === $uuid) {
				return $file;
			}
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('uuid', $qb->createNamedParameter($uuid))
			);

		/** @var File */
		$file = $this->findEntity($qb);

		$this->file = array_filter($this->file, fn ($f) => $f->getUuid() !== $uuid);
		$this->file[] = $file;

		return $file;
	}

	/**
	 * Return LibreSign file by signer UUID
	 */
	public function getBySignerUuid(?string $uuid = null): File {
		if (is_null($uuid) && !empty($this->file)) {
			return current($this->file);
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('f.*')
			->from($this->getTableName(), 'f')
			->join('f', 'libresign_sign_request', 'sr', $qb->expr()->eq('f.id', 'sr.file_id'))
			->where(
				$qb->expr()->eq('sr.uuid', $qb->createNamedParameter($uuid))
			);

		/** @var File */
		$file = $this->findEntity($qb);
		$this->file[] = $file;
		return $file;
	}

	/**
	 * Return LibreSign file by nodeId
	 */
	public function getByNodeId(?int $nodeId = null): File {
		$exists = array_filter($this->file, fn ($f) => $f->getNodeId() === $nodeId || $f->getSignedNodeId() === $nodeId);
		if (!empty($exists)) {
			return current($exists);
		}
		foreach ($this->file as $file) {
			if ($file->getNodeId() === $nodeId) {
				return $file;
			}
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->eq('signed_node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT))
				)
			);

		/** @var File */
		$file = $this->findEntity($qb);
		$this->file[] = $file;
		return $file;
	}

	public function fileIdExists(int $nodeId): bool {
		$exists = array_filter($this->file, fn ($f) => $f->getNodeId() === $nodeId || $f->getSignedNodeId() === $nodeId);
		if (!empty($exists)) {
			return true;
		}

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->eq('signed_node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT))
				)
			);

		$files = $this->findEntities($qb);
		if (!empty($files)) {
			foreach ($files as $file) {
				$this->file[] = $file;
			}
			return true;
		}
		return false;
	}

	/**
	 * @return File[]
	 */
	public function getFilesOfAccount(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('lf.*')
			->from($this->getTableName(), 'lf')
			->join('lf', 'libresign_id_docs', 'lid', 'lid.file_id = lf.id')
			->where(
				$qb->expr()->eq('lid.user_id', $qb->createNamedParameter($userId))
			);

		$cursor = $qb->executeQuery();
		$return = [];
		while ($row = $cursor->fetch()) {
			/** @var File */
			$file = $this->mapRowToEntity($row);
			$this->file[] = $file;
			$return[] = $file;
		}
		return $return;
	}

	public function getTextOfStatus(int|FileStatus $status): string {
		if (is_int($status)) {
			$status = FileStatus::from($status);
		}
		return $status->getLabel($this->l);
	}

	public function neutralizeDeletedUser(string $userId, string $displayName): void {
		$update = $this->db->getQueryBuilder();
		$qb = $this->db->getQueryBuilder();
		$qb->select('f.id')
			->addSelect('f.metadata')
			->from($this->getTableName(), 'f')
			->where($qb->expr()->eq('f.user_id', $qb->createNamedParameter($userId)));
		$cursor = $qb->executeQuery();
		while ($row = $cursor->fetch()) {
			$row['metadata'] = json_decode((string)$row['metadata'], true);
			$row['metadata']['deleted_account'] = [
				'account' => $userId,
				'display_name' => $displayName,
			];
			$update->update($this->getTableName())
				->set('user_id', $update->createNamedParameter(ICommentsManager::DELETED_USER))
				->set('metadata', $update->createNamedParameter($row['metadata'], IQueryBuilder::PARAM_JSON))
				->where($update->expr()->eq('id', $update->createNamedParameter($row['id'])));
			$update->executeStatement();
		}
	}

	/**
	 * @return File[]
	 */
	public function getChildrenFiles(int $parentId): array {
		$cached = array_filter($this->file, fn ($f) => $f->getParentFileId() === $parentId);
		if (!empty($cached) && count($cached) > 1) {
			return array_values($cached);
		}

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('parent_file_id', $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('node_type', $qb->createNamedParameter(NodeType::FILE->value))
			)
			->orderBy('id', 'ASC');

		$children = $this->findEntities($qb);

		foreach ($children as $child) {
			$this->file[] = $child;
		}

		return $children;
	}

	public function getParentEnvelope(int $fileId): ?File {
		$file = $this->getById($fileId);

		if (!$file->hasParent()) {
			return null;
		}

		return $this->getById($file->getParentFileId());
	}

	public function countChildrenFiles(int $envelopeId): int {
		$cached = array_filter($this->file, fn ($f) => $f->getParentFileId() === $envelopeId);
		if (!empty($cached)) {
			return count($cached);
		}

		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('parent_file_id', $qb->createNamedParameter($envelopeId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('node_type', $qb->createNamedParameter(NodeType::FILE->value))
			);

		$cursor = $qb->executeQuery();
		$row = $cursor->fetch();
		$cursor->closeCursor();

		return $row ? (int)$row['count'] : 0;
	}

	/**
	 * Find all files with a specific status
	 *
	 * @param int $status File status
	 * @return File[]
	 */
	public function findByStatus(int $status): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntities($qb);
	}

	/**
	 * Find files stuck in SIGNING_IN_PROGRESS status older than threshold
	 *
	 * @param \DateTime $staleThreshold Files created before this time will be returned
	 * @return File[]
	 */
	public function findStaleSigningInProgress(\DateTime $staleThreshold): array {
		// Fetch all files currently marked as SIGNING_IN_PROGRESS
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('status', $qb->createNamedParameter(FileStatus::SIGNING_IN_PROGRESS->value, IQueryBuilder::PARAM_INT))
			);

		$files = $this->findEntities($qb);

		// Filter by metadata timestamp `status_changed_at` when available;
		// fall back to createdAt for legacy records without metadata.
		$stale = [];
		foreach ($files as $file) {
			$meta = $file->getMetadata();
			if (is_array($meta) && isset($meta['status_changed_at'])) {
				try {
					$changedAt = new \DateTime($meta['status_changed_at']);
					if ($changedAt < $staleThreshold) {
						$stale[] = $file;
					}
					continue;
				} catch (\Throwable $e) {
					// Ignore parse errors and fall back below
				}
			}

			// Fallback: use createdAt when no metadata timestamp is present
			$created = $file->getCreatedAt();
			if ($created instanceof \DateTime && $created < $staleThreshold) {
				$stale[] = $file;
			}
		}

		return $stale;
	}
}
