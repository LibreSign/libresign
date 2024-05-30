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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\QBMapper;
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
		} catch (\Throwable $th) {
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
}
