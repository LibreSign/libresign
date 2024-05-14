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
