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
	private array $methodsByFileUser = [];
	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_identify_method');
	}

	/**
	 * @param integer $fileUserId
	 * @return array<IdentifyMethod>|null
	 */
	public function getIdentifyMethodsFromFileUserId(int $fileUserId): array {
		if (array_key_exists($fileUserId, $this->methodsByFileUser)) {
			return $this->methodsByFileUser[$fileUserId];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'im.method'
		)
			->from('libresign_identify_method', 'im')
			->where(
				$qb->expr()->eq('im.file_user_id', $qb->createNamedParameter($fileUserId, IQueryBuilder::PARAM_INT))
			);
		$cursor = $qb->executeQuery();
		$this->methodsByFileUser[$fileUserId] = [];
		while ($row = $cursor->fetch()) {
			$this->methodsByFileUser[$fileUserId][] = $row['method'];
		}
		return $this->methodsByFileUser[$fileUserId];
	}
}
