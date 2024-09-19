<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
