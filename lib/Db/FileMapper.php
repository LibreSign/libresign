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
use OCP\AppFramework\Db\Entity;
use OCP\Comments\ICommentsManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IL10N;

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\DB
 * @template-extends CachedQBMapper<File>
 */
class FileMapper extends CachedQBMapper {
	public function __construct(
		IDBConnection $db,
		private IL10N $l,
		ICacheFactory $cacheFactory,
	) {
		parent::__construct($db, $cacheFactory, 'libresign_file');
	}

	#[\Override]
	public function update(Entity $entity): File {
		$entityId = $entity->getId();
		if ($entityId !== null) {
			$cached = $this->cacheGet('id:' . $entityId);
			if ($cached instanceof File) {
				$nodeId = $cached->getNodeId();
				if ($nodeId !== null) {
					$this->cacheRemove('node_id:' . $nodeId);
				}
				$uuid = $cached->getUuid();
				if ($uuid !== '') {
					$this->cacheRemove('uuid:' . $uuid);
				}
			}
		}
		/** @var File */
		return parent::update($entity);
	}

	#[\Override]
	protected function cacheEntity(Entity $entity): void {
		parent::cacheEntity($entity);
		if ($entity instanceof File) {
			$nodeId = $entity->getNodeId();
			if ($nodeId !== null) {
				$this->cacheSet('node_id:' . $nodeId, $entity->getId());
			}
			$uuid = $entity->getUuid();
			if ($uuid !== '') {
				$this->cacheSet('uuid:' . $uuid, $entity->getId());
			}
		}
	}

	/**
	 * Return LibreSign file by ID
	 *
	 * @throws DoesNotExistException
	 * @return File Row of table libresign_file
	 */
	public function getById(int $id): File {
		$cached = $this->cacheGet('id:' . $id);
		if ($cached instanceof File) {
			return $cached;
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		/** @var File */
		$file = $this->findEntity($qb);
		$this->cacheEntity($file);

		return $file;
	}

	/**
	 * Return LibreSign file by signed hash
	 *
	 * @throws DoesNotExistException
	 * @return File Row of table libresign_file
	 */
	public function getBySignedHash(string $hash): File {
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
		$this->cacheEntity($file);
		return $file;
	}

	/**
	 * Return LibreSign file by file UUID
	 */
	public function getByUuid(string $uuid): File {
		$cachedId = $this->cacheGet('uuid:' . $uuid);
		if (is_int($cachedId) || (is_string($cachedId) && ctype_digit($cachedId))) {
			return $this->getById((int)$cachedId);
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('uuid', $qb->createNamedParameter($uuid))
			);

		/** @var File */
		$file = $this->findEntity($qb);
		$this->cacheEntity($file);

		return $file;
	}

	/**
	 * @return ?string userId for storage lookup (null for appdata)
	 * @throws DoesNotExistException
	 */
	public function getStorageUserIdByUuid(string $uuid): ?string {
		$qb = $this->db->getQueryBuilder();

		$qb->select('f.user_id', 'id.user_id AS id_docs_user_id', 'id.id AS id_docs_id')
			->from($this->getTableName(), 'f')
			->leftJoin('f', 'libresign_id_docs', 'id', $qb->expr()->eq('f.id', 'id.file_id'))
			->where($qb->expr()->eq('f.uuid', $qb->createNamedParameter($uuid)));

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (!$row) {
			throw new DoesNotExistException('File not found');
		}

		if ($row['id_docs_id'] !== null && $row['id_docs_user_id'] !== null) {
			return $row['id_docs_user_id'];
		}

		return $row['user_id'];
	}

	/**
	 * Return LibreSign file by signer UUID
	 */
	public function getBySignerUuid(?string $uuid = null): File {
		$qb = $this->db->getQueryBuilder();

		$qb->select('f.*')
			->from($this->getTableName(), 'f')
			->join('f', 'libresign_sign_request', 'sr', $qb->expr()->eq('f.id', 'sr.file_id'))
			->where(
				$qb->expr()->eq('sr.uuid', $qb->createNamedParameter($uuid))
			);

		/** @var File */
		$file = $this->findEntity($qb);
		$this->cacheEntity($file);
		return $file;
	}

	/**
	 * Return LibreSign file by nodeId
	 */
	public function getByNodeId(int $nodeId): File {
		$cachedId = $this->cacheGet('node_id:' . $nodeId);
		if (is_int($cachedId) || (is_string($cachedId) && ctype_digit($cachedId))) {
			return $this->getById((int)$cachedId);
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
		$this->cacheEntity($file);
		return $file;
	}

	public function nodeIdExists(int $nodeId): bool {
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
				$this->cacheEntity($file);
			}
			return true;
		}
		return false;
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
			$this->cacheEntity($child);
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
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('status', $qb->createNamedParameter(FileStatus::SIGNING_IN_PROGRESS->value, IQueryBuilder::PARAM_INT))
			);

		$files = $this->findEntities($qb);

		$stale = [];
		foreach ($files as $file) {
			$isStale = false;
			$meta = $file->getMetadata();

			if (is_array($meta) && isset($meta['status_changed_at'])) {
				try {
					$changedAt = new \DateTime($meta['status_changed_at']);
					$isStale = $changedAt < $staleThreshold;
				} catch (\Exception) {
				}
			}

			if (!$isStale) {
				$created = $file->getCreatedAt();
				$isStale = $created instanceof \DateTime && $created < $staleThreshold;
			}

			if ($isStale) {
				$stale[] = $file;
			}
		}

		return $stale;
	}
}
