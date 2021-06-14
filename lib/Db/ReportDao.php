<?php

namespace OCA\Libresign\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ReportDao {

	/** @var IDBConnection */
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	public function getFilesAssociatedFilesWithMe($userId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'f.uuid',
				'f.name',
				'f.callback',
				'f.node_id'
			)
			->selectAlias('u.uid_lower', 'requested_by_uid')
			->selectAlias('u.displayname', 'requested_by_dislpayname')
			->selectAlias('f.created_at', 'request_date')
			->selectAlias($qb->func()->max('fu.signed'), 'status_date')
			->from('libresign_file', 'f')
			->join('f', 'libresign_file_user', 'fu', 'fu.file_id = f.id')
			->leftJoin('f', 'users', 'u', 'f.user_id = u.uid')
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('f.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->eq('fu.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_INT))
				)
			)
			->groupBy(
				'f.uuid',
				'f.name',
				'f.callback',
				'f.node_id',
				'f.created_at',
				'u.uid_lower',
				'u.displayname'
			);

		return $qb->execute()->fetchAll();
	}
}
