<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Class FileElementsMapper
 *
 * @package OCA\Libresign\DB
 * @template-extends QBMapper<FileElement>
 */
class FileElementMapper extends QBMapper {
	/** @var FileElement[][] */
	private $cache = [];

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_file_element');
	}

	/**
	 * @return FileElement[]
	 */
	public function getByFileId(int $fileId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('fe.*')
			->from($this->getTableName(), 'fe')
			->where(
				$qb->expr()->eq('fe.file_id', $qb->createNamedParameter($fileId))
			);

		/** @var FileElement[] */
		return $this->findEntities($qb);
	}

	/**
	 * @return FileElement[]
	 */
	public function getByFileIdAndSignRequestId(int $fileId, ?int $signRequestId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('fe.*')
			->from($this->getTableName(), 'fe')
			->where(
				$qb->expr()->eq('fe.file_id', $qb->createNamedParameter($fileId))
			);
		if ($signRequestId) {
			$qb->andWhere(
				$qb->expr()->eq('fe.sign_request_id', $qb->createNamedParameter($signRequestId, IQueryBuilder::PARAM_INT))
			);
		}

		/** @var FileElement[] */
		return $this->findEntities($qb);
	}

	public function getById(int $id): FileElement {
		if (!isset($this->cache['documentElementId'][$id])) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('fe.*')
				->from($this->getTableName(), 'fe')
				->where(
					$qb->expr()->eq('fe.id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
				);

			/** @var FileElement */
			$this->cache['documentElementId'][$id] = $this->findEntity($qb);
		}
		return $this->cache['documentElementId'][$id];
	}
}
