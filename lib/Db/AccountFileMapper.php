<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\Entity;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\IURLGenerator;

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\DB
 * @template-extends QBMapper<AccountFile>
 */
class AccountFileMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private IURLGenerator $urlGenerator,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private FileTypeMapper $fileTypeMapper,
	) {
		parent::__construct($db, 'libresign_account_file');
	}

	public function getByUserAndType(string $userId, string $type): AccountFile {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('file_type', $qb->createNamedParameter($type))
			);

		/** @var AccountFile */
		return $this->findEntity($qb);
	}

	public function getByUserIdAndNodeId(string $userId, int $nodeId): AccountFile {
		$qb = $this->db->getQueryBuilder();

		$qb->select('laf.*')
			->from($this->getTableName(), 'laf')
			->join('laf', 'libresign_file', 'lf', 'laf.file_id = lf.id')
			->where(
				$qb->expr()->eq('laf.user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('lf.node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT))
			);

		/** @var AccountFile */
		return $this->findEntity($qb);
	}

	public function getByFileId(int $fileId): AccountFile {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);

		/** @var AccountFile */
		return $this->findEntity($qb);
	}

	public function delete(Entity $entity): Entity {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->tableName)
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($entity->getUserId(), Types::STRING)),
				$qb->expr()->eq('file_id', $qb->createNamedParameter($entity->getFileId(), Types::INTEGER))
			);
		$qb->executeStatement();
		$qb->resetQueryParts();
		$qb->delete('libresign_file')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($entity->getFileId(), Types::INTEGER))
			);
		$qb->executeStatement();
		return $entity;
	}
}
