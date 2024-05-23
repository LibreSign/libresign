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
}
