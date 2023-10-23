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

use OCA\Libresign\Helper\Pagination;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;

/**
 * Class FileUserMapper
 *
 * @package OCA\Libresign\DB
 */
class FileUserMapper extends QBMapper {
	/**
	 * @var FileUser[]
	 */
	private $signers = [];

	public function __construct(
		IDBConnection $db,
		protected IL10N $l10n,
	) {
		parent::__construct($db, 'libresign_file_user');
	}

	/**
	 * @inheritDoc
	 */
	public function update(Entity $entity): Entity {
		$fileUser = parent::update($entity);
		$filtered = array_filter($this->signers, fn ($e) => $e->getId() === $fileUser->getId());
		if (!empty($filtered)) {
			$this->signers[key($filtered)] = $fileUser;
		} else {
			$this->signers[] = $fileUser;
		}
		return $fileUser;
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
		foreach ($this->signers as $fileUser) {
			if ($fileUser->getUuid() === $uuid) {
				return $fileUser;
			}
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('uuid', $qb->createNamedParameter($uuid))
			);
		$fileUser = $this->findEntity($qb);
		$this->signers[] = $fileUser;
		return $fileUser;
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
			->join('fu', 'libresign_identify_method', 'im', 'fu.id = im.file_user_id')
			->where($qb->expr()->eq('im.method', $qb->createNamedParameter($identifyMethod->getEntity()->getMethod())))
			->andWhere($qb->expr()->eq('im.identifier_key', $qb->createNamedParameter($identifyMethod->getEntity()->getIdentifierKey())))
			->andWhere($qb->expr()->eq('im.identifier_value', $qb->createNamedParameter($identifyMethod->getEntity()->getIdentifierValue())))
			->andWhere($qb->expr()->eq('fu.file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Get all signers by fileId
	 *
	 * @return FileUser[]
	 */
	public function getByFileId(int $fileId): array {
		$signers = array_filter($this->signers, fn ($f) => $f->getFileId() === $fileId);
		if (!empty($signers)) {
			return $signers;
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);
		$signers = $this->findEntities($qb);
		foreach ($signers as $signer) {
			$this->signers[] = $signer;
		}
		return $signers;
	}

	public function getById(int $fileUserId): FileUser {
		foreach ($this->signers as $fileUser) {
			if ($fileUser->getFileId() === $fileUserId) {
				return $fileUser;
			}
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($fileUserId, IQueryBuilder::PARAM_INT))
			);
		$fileUser = $this->findEntity($qb);
		$this->signers[] = $fileUser;
		return $fileUser;
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
		$qb = $this->db->getQueryBuilder();

		$qb->select('fu.*')
			->from($this->getTableName(), 'fu')
			->join('fu', 'libresign_file', 'f', 'fu.file_id = f.id')
			->where(
				$qb->expr()->eq('f.node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT))
			);

		$signers = $this->findEntities($qb);
		return $signers;
	}

	/**
	 * Get all signers by File Uuid
	 *
	 * @param string $nodeId
	 * @return FileUser[]
	 */
	public function getByFileUuid(string $uuid) {
		$signers = array_filter($this->signers, fn ($f) => $f->getUuid() === $uuid);
		if (count($signers)) {
			return $signers;
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('fu.*')
			->from($this->getTableName(), 'fu')
			->join('fu', 'libresign_file', 'f', 'fu.file_id = f.id')
			->where(
				$qb->expr()->eq('f.uuid', $qb->createNamedParameter($uuid))
			);

		$signers = $this->findEntities($qb);
		$this->signers = array_merge($this->signers, $signers);
		return $signers;
	}

	public function getByUuidAndUserId(string $uuid, string $userId): FileUser {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName(), 'fu')
			->leftJoin('fu', 'libresign_identify_method', 'im', $qb->expr()->andX(
				$qb->expr()->eq('fu.id', 'im.file_user_id'),
				$qb->expr()->eq('im.method', $qb->createNamedParameter('account')),
				$qb->expr()->eq('im.identifier_key', $qb->createNamedParameter('uid'))
			))
			->where(
				$qb->expr()->eq('uuid', $qb->createNamedParameter($uuid))
			)
			->andWhere(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId))
			);

		$fileUser = $this->findEntity($qb);
		$this->signers[] = $fileUser;
		return $fileUser;
	}

	public function getByFileIdAndUserId(int $file_id, string $userId): FileUser {
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
				$qb->expr()->eq('f.node_id', $qb->createNamedParameter($file_id, IQueryBuilder::PARAM_INT))
			)
			->where(
				$qb->expr()->eq('im.identifier_value', $qb->createNamedParameter($userId))
			);

		return $this->findEntity($qb);
	}

	public function getByFileIdAndEmail(int $file_id, string $email): FileUser {
		$qb = $this->db->getQueryBuilder();

		$qb->select('fu.*')
			->from($this->getTableName(), 'fu')
			->join('fu', 'libresign_file', 'f', 'fu.file_id = f.id')
			->where(
				$qb->expr()->eq('f.node_id', $qb->createNamedParameter($file_id, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('fu.email', $qb->createNamedParameter($email))
			);

		return $this->findEntity($qb);
	}

	public function getByFileIdAndFileUserId(int $fileId, int $fileUserId): FileUser {
		$filtered = array_filter($this->signers, fn ($e) => $e->getId() === $fileUserId);
		if ($filtered) {
			return current($filtered);
		}
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

		$this->signers[] = $this->findEntity($qb);
		return end($this->signers);
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

	private function getFilesAssociatedFilesWithMeStmt(string $userId, ?string $email): Pagination {
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

		$or = [
			$qb->expr()->eq('f.user_id', $qb->createNamedParameter($userId)),
			$qb->expr()->andX(
				$qb->expr()->eq('im.identifier_key', $qb->createNamedParameter(IdentifyMethodService::IDENTIFY_ACCOUNT)),
				$qb->expr()->eq('im.identifier_value', $qb->createNamedParameter($userId))
			)
		];
		if ($email) {
			$or[] = $qb->expr()->andX(
				$qb->expr()->eq('im.identifier_key', $qb->createNamedParameter(IdentifyMethodService::IDENTIFY_EMAIL)),
				$qb->expr()->eq('im.identifier_value', $qb->createNamedParameter($email))
			);
		}
		$qb->where($qb->expr()->orX(...$or));

		$countQueryBuilderModifier = function (IQueryBuilder &$qb): void {
			/** @todo improve this to don't do two queries */
			$qb->select('f.id')
				->groupBy('f.id');
			$cursor = $qb->executeQuery();
			$ids = $cursor->fetchAll();

			$qb->resetQueryParts();
			$qb->selectAlias($qb->createNamedParameter(count($ids)), 'total');
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
						'displayName' =>
							array_reduce($identifyMethodsOfSigner, function (string $carry, IdentifyMethod $identifyMethod): string {
								if (!$carry && $identifyMethod->getMandatory()) {
									return $identifyMethod->getIdentifierValue();
								}
								return $carry;
							}, $signer->getDisplayName()),
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
				$files[$key]['status_text'] = $this->l10n->t('no signers');
			} elseif ($totalSigned === count($files[$key]['signers'])) {
				$files[$key]['status'] = 1;
				$files[$key]['status_text'] = $this->l10n->t('signed');
			} else {
				$files[$key]['status'] = 2;
				$files[$key]['status_text'] = $this->l10n->t('pending');
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
			->setTimestamp((int) $row['request_date'])
			->format('Y-m-d H:i:s');
		if (!empty($row['status_date'])) {
			$row['status_date'] = (new \DateTime())
				->setTimestamp((int) $row['status_date'])
				->format('Y-m-d H:i:s');
		}
		$row['file'] = [
			'type' => 'pdf',
			'url' => $url . $row['uuid'],
			'nodeId' => (int) $row['node_id'],
			'uuid' => $row['uuid'],
		];
		unset(
			$row['node_id'],
			$row['requested_by_uid'],
			$row['requested_by_dislpayname']
		);
		return $row;
	}
}
