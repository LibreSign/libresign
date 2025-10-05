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
 * @template-extends QBMapper<IdentifyMethod>
 */
class IdentifyMethodMapper extends QBMapper {
	/**
	 * @var IdentifyMethod[][]
	 */
	private array $methodsBySignRequest = [];
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_identify_method');
	}

	/**
	 * @return IdentifyMethod[]
	 */
	public function getIdentifyMethodsFromSignRequestId(int $signRequestId): array {
		if (!empty($this->methodsBySignRequest[$signRequestId])) {
			return $this->methodsBySignRequest[$signRequestId];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('im.*')
			->from('libresign_identify_method', 'im')
			->where(
				$qb->expr()->eq('im.sign_request_id', $qb->createNamedParameter($signRequestId, IQueryBuilder::PARAM_INT))
			);
		$cursor = $qb->executeQuery();
		$this->methodsBySignRequest[$signRequestId] = [];
		while ($row = $cursor->fetch()) {
			/** @var IdentifyMethod */
			$this->methodsBySignRequest[$signRequestId][] = $this->mapRowToEntity($row);
		}
		return $this->methodsBySignRequest[$signRequestId];
	}

	public function neutralizeDeletedUser(string $userId, string $displayName): void {
		$update = $this->db->getQueryBuilder();
		$qb = $this->db->getQueryBuilder();
		$qb->select('im.id')
			->addSelect('im.metadata')
			->from('libresign_identify_method', 'im')
			->where($qb->expr()->in('im.identifier_key', $qb->createNamedParameter(['account', 'email'], IQueryBuilder::PARAM_STR_ARRAY), IQueryBuilder::PARAM_STR_ARRAY))
			->andWhere($qb->expr()->eq('im.identifier_value', $qb->createNamedParameter($userId)));
		$cursor = $qb->executeQuery();
		while ($row = $cursor->fetch()) {
			if (is_string($row['metadata']) && !empty($row['metadata'])) {
				$row['metadata'] = json_decode($row['metadata'], true);
			} else {
				$row['metadata'] = [];
			}
			$row['metadata']['deleted_account'] = [
				'account' => $userId,
				'display_name' => $displayName,
			];
			$update->update('libresign_identify_method')
				->set('identifier_value', $update->createNamedParameter(ICommentsManager::DELETED_USER))
				->set('metadata', $update->createNamedParameter($row['metadata'], IQueryBuilder::PARAM_JSON))
				->where($update->expr()->eq('id', $update->createNamedParameter($row['id'])));
			$update->executeStatement();
		}
	}

	/**
	 * @return array<string, string>[]
	 */
	public function searchByIdentifierValue(string $search, string $userId, string $method, int $limit = 20, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();

		$latestQb = $this->db->getQueryBuilder();
		$latestQb->select('im2.identifier_key')
			->addSelect('im2.identifier_value')
			->addSelect($latestQb->func()->max('sr2.created_at', 'created_at'))
			->from('libresign_identify_method', 'im2')
			->join('im2', 'libresign_sign_request', 'sr2',
				$latestQb->expr()->eq('sr2.id', 'im2.sign_request_id')
			)
			->join('im2', 'libresign_file', 'f2',
				$latestQb->expr()->eq('f2.id', 'sr2.file_id')
			)
			->where($latestQb->expr()->eq('f2.user_id', $latestQb->createNamedParameter($userId)));
		if (!empty($method)) {
			$latestQb->andWhere($latestQb->expr()->eq('im2.identifier_key', $latestQb->createNamedParameter($method)));
		}
		$latestQb->andWhere(
			$latestQb->expr()->orX(
				$latestQb->expr()->iLike(
					'im2.identifier_value',
					$latestQb->createNamedParameter('%' . $this->db->escapeLikeParameter($search) . '%')
				),
				$latestQb->expr()->iLike(
					'sr2.display_name',
					$latestQb->createNamedParameter('%' . $this->db->escapeLikeParameter($search) . '%')
				)
			)
		)
			->groupBy('im2.identifier_key')
			->addGroupBy('im2.identifier_value');

		foreach ($latestQb->getParameters() as $name => $value) {
			$qb->setParameter($name, $value);
		}

		$qb->select('im.identifier_key', 'im.identifier_value', 'sr.display_name')
			->from('libresign_identify_method', 'im')
			->join('im', $qb->createFunction('(' . $latestQb->getSQL() . ')'), 'latest',
				$qb->expr()->andX(
					$qb->expr()->eq('latest.identifier_key', 'im.identifier_key'),
					$qb->expr()->eq('latest.identifier_value', 'im.identifier_value')
				)
			)
			->join('im', 'libresign_sign_request', 'sr',
				$qb->expr()->eq('sr.id', 'im.sign_request_id'),
			)
			->setMaxResults($limit)
			->setFirstResult($offset);

		$cursor = $qb->executeQuery();
		$return = [];
		while ($row = $cursor->fetch()) {
			$return[] = $row;
		}
		return $return;
	}
}
