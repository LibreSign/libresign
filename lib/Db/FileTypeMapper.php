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
use OCP\IDBConnection;
use OCP\IL10N;

/**
 * Class FileTypeMapper
 *
 * @package OCA\Libresign\DB
 * @template-extends QBMapper<FileType>
 */
class FileTypeMapper extends QBMapper {
	private $types = [];

	public function __construct(
		IDBConnection $db,
		private IL10N $l,
	) {
		parent::__construct($db, 'libresign_file_type');
	}

	public function getNameOfType($type): string {
		if (!isset($this->types[$type])) {
			$this->getTypes();
		}
		return $this->types[$type]['name'];
	}

	public function getDescriptionOfType($type): string {
		if (!isset($this->types[$type])) {
			$this->getTypes();
		}
		return $this->types[$type]['description'];
	}

	public function getTypes(): array {
		if (empty($this->types)) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName());
			$cursor = $qb->executeQuery();

			$this->types['IDENTIFICATION'] = [
				'type' => 'IDENTIFICATION',
				'name' => $this->l->t('Identification Document'),
				'description' => $this->l->t('Identification Document'),
			];
			while ($row = $cursor->fetch()) {
				$this->types[$row['type']] = $this->mapRowToEntity($row);
			}
		}
		return $this->types;
	}
}
