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
}
