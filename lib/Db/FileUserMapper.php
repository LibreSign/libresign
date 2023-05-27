<?php

namespace OCA\Libresign\Db;

use OCA\Libresign\Helper\Pagination;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUser;

/**
 * Class FileUserMapper
 *
 * @package OCA\Libresign\DB
 */
class FileUserMapper extends QBMapper {
	private $signers = [];

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

	public function getByIdentifyMethodAndFileId(IIdentifyMethod $identifyMethod, int $fileId): \OCP\AppFramework\Db\Entity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('fu.*')
			->from($this->getTableName(), 'fu')
			->join('fu', 'libresign_identify_method', 'im', 'fu.file_id = im.file_user_id')
			->where($qb->expr()->eq('im.method', $qb->createNamedParameter($identifyMethod->getEntity()->getMethod())))
			->andWhere($qb->expr()->eq('im.identifier_key', $qb->createNamedParameter($identifyMethod->getEntity()->getIdentifierKey())))
			->andWhere($qb->expr()->eq('im.identifier_value', $qb->createNamedParameter($identifyMethod->getEntity()->getIdentifierValue())))
			->andWhere($qb->expr()->eq('fu.file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Get all signers by fileId
	 *
	 * @return FileUser|FileUser[]
	 * @psalm-return FileUser|array<int, FileUser>
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


			->leftJoin('fu', 'libresign_identify_method', 'im', $qb->expr()->andX(
				$qb->expr()->eq('fu.id', 'im.file_user_id'),
				$qb->expr()->eq('im.method', $qb->createNamedParameter('account')),
				$qb->expr()->eq('im.identifier_key', $qb->createNamedParameter('uid'))
			))
			->leftJoin('f', 'users', 'u', 'im.identifier_value = u.uid')
			->where(
				$qb->expr()->eq('f.node_id', $qb->createNamedParameter($file_id))
			)
			->where(
				$qb->expr()->eq('im.identifier_value', $qb->createNamedParameter($userId))
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
	public function getFilesAssociatedFilesWithMeFormatted(
		IUser $user,
		string $url,
		int $page = null,
		int $length = null
	): array {
		$pagination = $this->getFilesAssociatedFilesWithMeStmt($user->getUID(), $user->getEMailAddress());
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
		$identifyMethods = $this->getIdentifyMethodsFromSigners($signers);
		$return['data'] = $this->associateAllAndFormat($user, $data, $signers, $identifyMethods);
		$return['pagination'] = $pagination;
		return $return;
	}

	/**
	 * @param array<FileUser> $fileUsers
	 * @return array<array-key, array<array-key, \OCP\AppFramework\Db\Entity&\OCA\Libresign\Db\IdentifyMethod>>
	 */
	private function getIdentifyMethodsFromSigners(array $fileUsers): array {
		$fileUserIds = array_map(function (FileUser $fileUser): int {
			return $fileUser->getId();
		}, $fileUsers);
		if (!$fileUserIds) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('im.*')
			->from('libresign_identify_method', 'im')
			->where(
				$qb->expr()->in('im.file_user_id', $qb->createParameter('fileUserIds'))
			)
			->orderBy('im.mandatory', 'DESC')
			->addOrderBy('im.identified_at_date', 'ASC');

		$return = [];
		foreach (array_chunk($fileUserIds, 1000) as $fileUserIdsChunk) {
			$qb->setParameter('fileUserIds', $fileUserIdsChunk, IQueryBuilder::PARAM_INT_ARRAY);
			$cursor = $qb->executeQuery();
			while ($row = $cursor->fetch()) {
				$identifyMethod = new IdentifyMethod();
				$return[$row['file_user_id']][$row['method']] = $identifyMethod->fromRow($row);
			}
		}
		return $return;
	}

	private function getFilesAssociatedFilesWithMeStmt(string $userId, string $email): Pagination {
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
			->leftJoin('f', 'libresign_identify_method', 'im', $qb->expr()->eq('fu.id', 'im.file_user_id'))
			->join('f', 'users', 'u', 'f.user_id = u.uid')
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('f.user_id', $qb->createNamedParameter($userId)),
					$qb->expr()->andX(
						$qb->expr()->eq('im.identifier_key', $qb->createNamedParameter(IdentifyMethodService::IDENTIFY_ACCOUNT)),
						$qb->expr()->eq('im.identifier_value', $qb->createNamedParameter($userId))
					),
					$qb->expr()->andX(
						$qb->expr()->eq('im.identifier_key', $qb->createNamedParameter(IdentifyMethodService::IDENTIFY_EMAIL)),
						$qb->expr()->eq('im.identifier_value', $qb->createNamedParameter($email))
					)
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

		$countQueryBuilderModifier = function (IQueryBuilder &$qb) use ($userId, $email): void {
			$query = $qb->getConnection()->getQueryBuilder();
			$subQuery = $qb->getConnection()->getQueryBuilder();
			$subQuery->resetQueryParts(['select', 'groupBy']);
			$subQuery->select('f.id')
				->groupBy('f.id');

			$query->setParameters($subQuery->getParameters());
			$query->selectAlias($query->func()->count(), 'total_results')
				->from('libresign_file', 'f')
				->where($query->expr()->in('f.id', $query->createFunction($subQuery->getSQL())));
			$qb = $query;
		};

		$pagination = new Pagination($qb, $countQueryBuilderModifier);
		return $pagination;
	}

	/**
	 * @param IUser $userId
	 * @param array $files
	 * @param array<FileUser> $signers
	 * @param array<array-key, array<array-key, \OCP\AppFramework\Db\Entity&\OCA\Libresign\Db\IdentifyMethod>> $identifyMethods
	 */
	private function associateAllAndFormat(IUser $user, array $files, array $signers, array $identifyMethods): array {
		foreach ($files as $key => $file) {
			$totalSigned = 0;
			foreach ($signers as $signerKey => $signer) {
				if ($signer->getFileId() === $file['id']) {
					/** @var array<IdentifyMethod> */
					$identifyMethodsOfSigner = $identifyMethods[$signer->getId()] ?? [];
					$data = [
						'email' => array_reduce($identifyMethodsOfSigner, function (string $carry, IdentifyMethod $identifyMethod): string {
							if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
								return $identifyMethod->getIdentifierValue();
							}
							return $carry;
						}, ''),
						'description' => $signer->getDescription(),
						'displayName' => $signer->getDisplayName(),
						'request_sign_date' => (new \DateTime())
							->setTimestamp($signer->getCreatedAt())
							->format('Y-m-d H:i:s'),
						'sign_date' => null,
						'uid' => array_reduce($identifyMethodsOfSigner, function (string $carry, IdentifyMethod $identifyMethod): string {
							if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_ACCOUNT) {
								return $identifyMethod->getIdentifierValue();
							}
							return $carry;
						}, ''),
						'fileUserId' => $signer->getId(),
						'me' => array_reduce($identifyMethodsOfSigner, function (bool $carry, IdentifyMethod $identifyMethod) use ($user): bool {
							if (!$user->getEMailAddress()) {
								return false;
							}
							if ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_ACCOUNT) {
								if ($user->getUID() === $identifyMethod->getIdentifierValue()) {
									return true;
								}
							} elseif ($identifyMethod->getIdentifierKey() === IdentifyMethodService::IDENTIFY_EMAIL) {
								if (!$user->getEMailAddress()) {
									return false;
								}
								if ($user->getEMailAddress() === $identifyMethod->getIdentifierValue()) {
									return true;
								}
							}
							return $carry;
						}, false),
						'identifyMethods' => array_map(function (IdentifyMethod $identifyMethod) use ($signer): array {
							return [
								'method' => $identifyMethod->getMethod(),
								'mandatory' => $identifyMethod->getMandatory(),
								'identifiedAtDate' => $identifyMethod->getIdentifiedAtDate()
							];
						}, array_values($identifyMethodsOfSigner)),
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
