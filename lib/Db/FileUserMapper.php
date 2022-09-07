<?php

namespace OCA\Libresign\Db;

use OCA\Libresign\Helper\Pagination;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Class FileUserMapper
 *
 * @package OCA\Libresign\DB
 *
 * @codeCoverageIgnore
 * @method FileUser insert(FileUser $entity)
 * @method FileUser update(FileUser $entity)
 * @method FileUser insertOrUpdate(FileUser $entity)
 * @method FileUser delete(FileUser $entity)
 */
class FileUserMapper extends QBMapper {
	/** @var FileUser[][] */
	private $signers = [];

	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_file_user');
	}

	/**
	 * Returns all users who have not signed
	 *
	 * @return \OCP\AppFramework\Db\Entity[] all fetched entities
	 *
	 * @psalm-return array<\OCP\AppFramework\Db\Entity>
	 */
	public function findUnsigned(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->isNull('signed')
			);

		return $this->findEntities($qb);
	}

	/**
	 * Get file user by UUID
	 *
	 * @param string $uuid
	 * @return FileUser
	 * @throws DoesNotExistException
	 */
	public function getByUuid(string $uuid): FileUser {
		if (!isset($this->signers['fileUserUuid'][$uuid])) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('*')
				->from($this->getTableName())
				->where(
					$qb->expr()->eq('uuid', $qb->createNamedParameter($uuid))
				);

			$this->signers['fileUserUuid'][$uuid] = $this->findEntity($qb);
		}
		return $this->signers['fileUserUuid'][$uuid];
	}

	public function getByEmailAndFileId(string $email, int $fileId): \OCP\AppFramework\Db\Entity {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('email', $qb->createNamedParameter($email))
			)
			->andWhere(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * Get all signers by fileId
	 *
	 * @param int $fileId
	 *
	 * @return FileUser|\OCP\AppFramework\Db\Entity[]
	 *
	 * @psalm-return FileUser|array<int, \OCP\AppFramework\Db\Entity>
	 */
	public function getByFileId(int $fileId) {
		if (!isset($this->signers['fileId'][$fileId])) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('*')
				->from($this->getTableName())
				->where(
					$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
				);
			$signers = $this->findEntities($qb);
			$this->signers['fileId'][$fileId] = [];
			foreach ($signers as $signer) {
				$this->signers['fileId'][$fileId][$signer->getId()] = $signer;
			}
		}
		return $this->signers['fileId'][$fileId];
	}

	public function getById(int $fileUserId): FileUser {
		if (!isset($this->signers['id'][$fileUserId])) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('*')
				->from($this->getTableName())
				->where(
					$qb->expr()->eq('id', $qb->createNamedParameter($fileUserId, IQueryBuilder::PARAM_INT))
				);
			$this->signers['id'][$fileUserId] = $this->findEntity($qb);
		}
		return $this->signers['id'][$fileUserId];
	}

	/**
	 * Get all signers by multiple fileId
	 *
	 * @param array $fileId
	 * @return FileUser[]
	 */
	public function getByMultipleFileId(array $fileId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT_ARRAY))
			);

		return $this->findEntities($qb);
	}

	/**
	 * Get all signers by fileId
	 *
	 *
	 * @param int $nodeId
	 *
	 * @return FileUser[]
	 */
	public function getByNodeId(int $nodeId) {
		if (!isset($this->signers['nodeId'][$nodeId])) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('fu.*')
				->from($this->getTableName(), 'fu')
				->join('fu', 'libresign_file', 'f', 'fu.file_id = f.id')
				->where(
					$qb->expr()->eq('f.node_id', $qb->createNamedParameter($nodeId))
				);

			$this->signers['nodeId'][$nodeId] = $this->findEntities($qb);
		}
		return $this->signers['nodeId'][$nodeId];
	}

	/**
	 * Get all signers by File Uuid
	 *
	 * @param string $nodeId
	 * @return FileUser[]
	 */
	public function getByFileUuid(string $uuid) {
		if (!isset($this->signers['fileUuid'][$uuid])) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('fu.*')
				->from($this->getTableName(), 'fu')
				->join('fu', 'libresign_file', 'f', 'fu.file_id = f.id')
				->where(
					$qb->expr()->eq('f.uuid', $qb->createNamedParameter($uuid))
				);

			$this->signers['fileUuid'][$uuid] = $this->findEntities($qb);
		}
		return $this->signers['fileUuid'][$uuid];
	}

	public function getByUuidAndUserId(string $uuid, string $userId): FileUser {
		if (!isset($this->signers['fileUserUuid'][$uuid])) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('*')
				->from($this->getTableName())
				->where(
					$qb->expr()->eq('uuid', $qb->createNamedParameter($uuid))
				)
				->andWhere(
					$qb->expr()->eq('user_id', $qb->createNamedParameter($userId))
				);

			$this->signers['fileUserUuid'][$uuid] = $this->findEntity($qb);
		}
		return $this->signers['fileUserUuid'][$uuid];
	}

	public function getByFileIdAndUserId(string $file_id, string $userId): FileUser {
		$qb = $this->db->getQueryBuilder();

		$qb->select('fu.*')
			->from($this->getTableName(), 'fu')
			->join('fu', 'libresign_file', 'f', 'fu.file_id = f.id')
			->where(
				$qb->expr()->eq('f.node_id', $qb->createNamedParameter($file_id))
			)
			->andWhere(
				$qb->expr()->eq('fu.user_id', $qb->createNamedParameter($userId))
			);

		return $this->findEntity($qb);
	}

	public function getByFileIdAndEmail(string $file_id, string $email): FileUser {
		$qb = $this->db->getQueryBuilder();

		$qb->select('fu.*')
			->from($this->getTableName(), 'fu')
			->join('fu', 'libresign_file', 'f', 'fu.file_id = f.id')
			->where(
				$qb->expr()->eq('f.node_id', $qb->createNamedParameter($file_id))
			)
			->andWhere(
				$qb->expr()->eq('fu.email', $qb->createNamedParameter($email))
			);

		return $this->findEntity($qb);
	}

	public function getByFileIdAndFileUserId(int $fileId, int $fileUserId): FileUser {
		if (!isset($this->signers['fileId'][$fileId][$fileUserId])) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('fu.*')
				->from($this->getTableName(), 'fu')
				->join('fu', 'libresign_file', 'f', 'fu.file_id = f.id')
				->where(
					$qb->expr()->eq('f.node_id', $qb->createNamedParameter($fileId))
				)
				->andWhere(
					$qb->expr()->eq('fu.id', $qb->createNamedParameter($fileUserId))
				);

			$this->signers['fileId'][$fileId][$fileUserId] = $this->findEntity($qb);
		}
		return $this->signers['fileId'][$fileId][$fileUserId];
	}

	/**
	 * @return array<\OCA\Libresign\Helper\Pagination|array>
	 * @psalm-return array{pagination: \OCA\Libresign\Helper\Pagination, data: array}
	 */
	public function getFilesAssociatedFilesWithMeFormatted(string $userId, string $url, int $page = null, int $length = null): array {
		$pagination = $this->getFilesAssociatedFilesWithMeStmt($userId);
		$pagination->setMaxPerPage($length);
		$pagination->setCurrentPage($page);
		$currentPageResults = $pagination->getCurrentPageResults();

		$data = [];
		$fileIds = [];

		foreach ($currentPageResults as $row) {
			$fileIds[] = $row['id'];
			$data[] = $this->formatListRow($row, $url);
		}
		$signers = $this->getByMultipleFileId($fileIds);
		$return['data'] = $this->assocFileToFileUserAndFormat($userId, $data, $signers);
		$return['pagination'] = $pagination;
		return $return;
	}

	/**
	 * @return Pagination
	 */
	private function getFilesAssociatedFilesWithMeStmt(string $userId, bool $count = false): Pagination {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'f.id',
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
			->leftJoin('f', 'libresign_file_user', 'fu', 'fu.file_id = f.id')
			->leftJoin('f', 'users', 'u', 'f.user_id = u.uid')
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('f.user_id', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('fu.user_id', $qb->createNamedParameter($userId))
				)
			)
			->groupBy(
				'f.id',
				'f.uuid',
				'f.name',
				'f.callback',
				'f.node_id',
				'f.created_at',
				'u.uid_lower',
				'u.displayname'
			);

		$countQueryBuilderModifier = function (IQueryBuilder &$qb) use ($userId): void {
			$count = $qb->getConnection()->getQueryBuilder();
			$count->selectAlias($count->func()->count(), 'total_results')
				->from('libresign_file', 'f')
				->where(
					$count->expr()->eq('f.user_id', $count->createNamedParameter($userId)),
				)
				->setMaxResults(1);
			$qb = $count;
		};

		$pagination = new Pagination($qb, $countQueryBuilderModifier);
		return $pagination;
	}

	private function assocFileToFileUserAndFormat(string $userId, array $files, array $signers): array {
		foreach ($files as $key => $file) {
			$totalSigned = 0;
			foreach ($signers as $signerKey => $signer) {
				if ($signer->getFileId() === $file['id']) {
					$data = [
						'email' => $signer->getEmail(),
						'description' => $signer->getDescription(),
						'displayName' => $signer->getDisplayName(),
						'request_sign_date' => (new \DateTime())
							->setTimestamp($signer->getCreatedAt())
							->format('Y-m-d H:i:s'),
						'sign_date' => null,
						'uid' => $signer->getUserId(),
						'fileUserId' => $signer->getId(),
						'me' => $userId === $signer->getUserId()
					];

					if ($data['me']) {
						$data['sign_uuid'] = $signer->getUuid();
					}

					if ($signer->getSigned()) {
						$data['sign_date'] = (new \DateTime())
							->setTimestamp($signer->getSigned())
							->format('Y-m-d H:i:s');
						$totalSigned++;
					}
					$files[$key]['signers'][] = $data;
					unset($signers[$signerKey]);
				}
			}
			if (empty($files[$key]['signers'])) {
				$files[$key]['signers'] = [];
				$files[$key]['status'] = 0;
				$files[$key]['status_text'] = 'no signers';
			} elseif ($totalSigned === count($files[$key]['signers'])) {
				$files[$key]['status'] = 1;
				$files[$key]['status_text'] = 'signed';
			} else {
				$files[$key]['status'] = 2;
				$files[$key]['status_text'] = 'pending';
			}
			unset($files[$key]['id']);
		}
		return $files;
	}

	/**
	 * @return ((int|string)[]|mixed|string)[]
	 *
	 * @psalm-return array{status_date: string, file: array{type: 'pdf', url: string, nodeId: int}}
	 */
	private function formatListRow(array $row, string $url): array {
		$row['id'] = (int) $row['id'];
		$row['requested_by'] = [
			'uid' => $row['requested_by_uid'],
			'displayName' => $row['requested_by_dislpayname']
		];
		$row['request_date'] = (new \DateTime())
			->setTimestamp($row['request_date'])
			->format('Y-m-d H:i:s');
		if (!empty($row['status_date'])) {
			$row['status_date'] = (new \DateTime())
				->setTimestamp($row['status_date'])
				->format('Y-m-d H:i:s');
		}
		$row['file'] = [
			'type' => 'pdf',
			'url' => $url . $row['uuid'],
			'nodeId' => (int) $row['node_id']
		];
		unset(
			$row['node_id'],
			$row['requested_by_uid'],
			$row['requested_by_dislpayname']
		);
		return $row;
	}
}
