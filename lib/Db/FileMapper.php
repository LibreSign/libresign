<?php

namespace OCA\Libresign\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\DB
 */
class FileMapper extends QBMapper {
	/** @var File[] */
	private $file = [];

	public function __construct(
		IDBConnection $db,
		private IL10N $l
	) {
		parent::__construct($db, 'libresign_file');
	}

	/**
	 * Return LibreSign file by ID
	 *
	 * @return File Row of table libresign_file
	 */
	public function getById(int $id): File {
		foreach ($this->file as $file) {
			if ($file->getId() === $id) {
				return $file;
			}
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$file = $this->findEntity($qb);
		$this->file[] = $file;
		return $file;
	}

	/**
	 * Return LibreSign file by UUID
	 */
	public function getByUuid(?string $uuid = null): File {
		if (is_null($uuid) && !empty($this->file)) {
			return current($this->file);
		}
		foreach ($this->file as $file) {
			if ($file->getUuid() === $uuid) {
				return $file;
			}
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('uuid', $qb->createNamedParameter($uuid))
			);

		$file = $this->findEntity($qb);
		$this->file[] = $file;
		return $file;
	}

	/**
	 * Return LibreSign file by fileId
	 */
	public function getByFileId(?int $fileId = null): File {
		if (is_null($fileId) && !empty($this->file)) {
			return current($this->file);
		}
		foreach ($this->file as $file) {
			if ($file->getNodeId() === $fileId) {
				return $file;
			}
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('node_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->eq('signed_node_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
				)
			);

		$file = $this->findEntity($qb);
		$this->file[] = $file;
		return $file;
	}

	/**
	 * @return File[]
	 */
	public function getFilesOfAccount(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('lf.*')
			->from($this->getTableName(), 'lf')
			->join('lf', 'libresign_account_file', 'laf', 'laf.file_id = lf.id')
			->where(
				$qb->expr()->eq('laf.user_id', $qb->createNamedParameter($userId))
			);

		$cursor = $qb->executeQuery();
		$return = [];
		while ($row = $cursor->fetch()) {
			$file = $this->mapRowToEntity($row);
			$this->file[] = $file;
			$return[] = $file;
		}
		return $return;
	}

	public function getFileType(int $id): string {
		$fullOuterJoin = $this->db->getQueryBuilder();
		$fullOuterJoin->select($fullOuterJoin->expr()->literal(1));

		$qb = $this->db->getQueryBuilder();
		$qb
			->selectAlias('f.id', 'file')
			->selectAlias('sf.signed_node_id', 'signed_file')
			->selectAlias('ue.id', 'user_element')
			->selectAlias('fe.id', 'file_element')
			->from($qb->createFunction('(' . $fullOuterJoin->getSQL() . ')'), 'foj')
			->leftJoin('foj', 'libresign_file', 'f', $qb->expr()->eq('f.node_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->leftJoin('foj', 'libresign_file', 'sf', $qb->expr()->eq('sf.signed_node_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->leftJoin('foj', 'libresign_user_element', 'ue', $qb->expr()->eq('ue.file_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->leftJoin('foj', 'libresign_file_element', 'fe', $qb->expr()->eq('fe.file_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$cursor = $qb->executeQuery();
		$row = $cursor->fetch();
		if ($row) {
			foreach ($row as $key => $value) {
				if ($value) {
					return $key;
				}
			}
		}
		return 'not_libresign_file';
	}

	public function getTextOfStatus(int $status): ?string {
		switch ($status) {
			case File::STATUS_DRAFT:
				return $this->l->t('draft');
			case File::STATUS_ABLE_TO_SIGN:
				return $this->l->t('able to sign');
			case File::STATUS_PARTIAL_SIGNED:
				return $this->l->t('partially signed');
			case File::STATUS_SIGNED:
				return $this->l->t('signed');
			case File::STATUS_DELETED:
				return $this->l->t('deleted');
		}
		return '';
	}
}
