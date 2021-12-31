<?php

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use OCP\IL10N;

/**
 * Class FileTypeMapper
 *
 * @package OCA\Libresign\DB
 *
 * @codeCoverageIgnore
 * @method File insert(File $entity)
 * @method File update(File $entity)
 * @method File insertOrUpdate(File $entity)
 * @method File delete(File $entity)
 */
class FileTypeMapper extends QBMapper {
	/** @var IL10N */
	private $l;
	private $types = [];

	/**
	 * @param IDBConnection $db
	 */
	public function __construct(
		IDBConnection $db,
		IL10N $l
	) {
		$this->l = $l;
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
