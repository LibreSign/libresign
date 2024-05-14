<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
