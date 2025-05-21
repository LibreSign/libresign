<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\Comments\ICommentsManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Class FileElementsMapper
 *
 * @package OCA\Libresign\DB
 * @template-extends QBMapper<UserElement>
 */
class UserElementMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_user_element');
	}

	private function getQueryBuilder(array $data): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb->select('ue.*')
			->from($this->getTableName(), 'ue');

		if (isset($data['id'])) {
			$qb->andWhere(
				$qb->expr()->eq('ue.id', $qb->createNamedParameter($data['id'], IQueryBuilder::PARAM_INT))
			);
		}
		if (isset($data['file_id'])) {
			$qb->andWhere(
				$qb->expr()->eq('ue.file_id', $qb->createNamedParameter($data['file_id'], IQueryBuilder::PARAM_INT))
			);
		}
		if (isset($data['type'])) {
			$qb->andWhere(
				$qb->expr()->eq('ue.type', $qb->createNamedParameter($data['type']))
			);
		}
		if (isset($data['user_id'])) {
			$qb->andWhere(
				$qb->expr()->eq('ue.user_id', $qb->createNamedParameter($data['user_id']))
			);
		}
		return $qb;
	}

	public function findOne(array $data): UserElement {
		$qb = $this->getQueryBuilder($data);
		try {
			$row = $this->findOneQuery($qb);
		} catch (\Throwable) {
			$qb->andWhere(
				$qb->expr()->eq('ue.starred', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			);
			$row = $this->findOneQuery($qb);
		}
		/** @var UserElement */
		$userElement = $this->mapRowToEntity($row);
		return $userElement;
	}

	/**
	 * @return UserElement[]
	 */
	public function findMany(array $data): array {
		$qb = $this->getQueryBuilder($data);
		/** @var UserElement[] */
		return $this->findEntities($qb);
	}

	public function neutralizeDeletedUser(string $userId, string $displayName): void {
		$update = $this->db->getQueryBuilder();
		$qb = $this->db->getQueryBuilder();
		$qb->select('ue.id')
			->addSelect('ue.metadata')
			->from($this->getTableName(), 'ue')
			->where($qb->expr()->eq('ue.user_id', $qb->createNamedParameter($userId)));
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
}
