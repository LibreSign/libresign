<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use DateTimeInterface;
use OCA\Libresign\Helper\Pagination;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDateTimeFormatter;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Class SignRequestMapper
 *
 * @package OCA\Libresign\DB
 * @template-extends QBMapper<SignRequest>
 */
class SignRequestMapper extends QBMapper {
	/**
	 * @var SignRequest[]
	 */
	private $signers = [];
	private bool $firstNotification = false;

	public function __construct(
		IDBConnection $db,
		protected IL10N $l10n,
		protected FileMapper $fileMapper,
		private IUserManager $userManager,
		private IDateTimeFormatter $dateTimeFormatter,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($db, 'libresign_sign_request');
	}

	/**
	 * @return boolean true when is the first notification
	 */
	public function incrementNotificationCounter(SignRequest $signRequest, string $method): bool {
		$this->db->beginTransaction();
		try {
			$fromDatabase = $this->getById($signRequest->getId());
			$metadata = $fromDatabase->getMetadata();
			if (!isset($metadata['notify'])) {
				$this->firstNotification = true;
			}
			$metadata['notify'][] = [
				'method' => $method,
				'date' => time(),
			];
			$fromDatabase->setMetadata($metadata);
			$this->update($fromDatabase);
			$this->db->commit();
		} catch (\Throwable) {
			$this->db->rollBack();
		}
		return $this->firstNotification;
	}

	/**
	 * @inheritDoc
	 */
	public function update(Entity $entity): SignRequest {
		/** @var SignRequest */
		$signRequest = parent::update($entity);
		$filtered = array_filter($this->signers, fn ($e) => $e->getId() === $signRequest->getId());
		if (!empty($filtered)) {
			$this->signers[key($filtered)] = $signRequest;
		} else {
			$this->signers[] = $signRequest;
		}
		return $signRequest;
	}

	/**
	 * Get sign request by UUID
	 *
	 * @throws DoesNotExistException
	 */
	public function getByUuid(string $uuid): SignRequest {
		foreach ($this->signers as $signRequest) {
			if ($signRequest->getUuid() === $uuid) {
				return $signRequest;
			}
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('uuid', $qb->createNamedParameter($uuid))
			);
		/** @var SignRequest */
		$signRequest = $this->findEntity($qb);
		if (!array_filter($this->signers, fn ($s) => $s->getId() === $signRequest->getId())) {
			$this->signers[] = $signRequest;
		}
		return $signRequest;
	}

	public function getByEmailAndFileId(string $email, int $fileId): SignRequest {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('email', $qb->createNamedParameter($email))
			)
			->andWhere(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);
		/** @var SignRequest */
		return $this->findEntity($qb);
	}

	public function getByIdentifyMethodAndFileId(IIdentifyMethod $identifyMethod, int $fileId): SignRequest {
		$qb = $this->db->getQueryBuilder();
		$qb->select('sr.*')
			->from($this->getTableName(), 'sr')
			->join('sr', 'libresign_identify_method', 'im', 'sr.id = im.sign_request_id')
			->where($qb->expr()->eq('im.identifier_key', $qb->createNamedParameter($identifyMethod->getEntity()->getIdentifierKey())))
			->andWhere($qb->expr()->eq('im.identifier_value', $qb->createNamedParameter($identifyMethod->getEntity()->getIdentifierValue())))
			->andWhere($qb->expr()->eq('sr.file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		/** @var SignRequest */
		return $this->findEntity($qb);
	}

	/**
	 * Get all signers by fileId
	 *
	 * @return SignRequest[]
	 */
	public function getByFileId(int $fileId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);
		/** @var SignRequest[] */
		$signers = $this->findEntities($qb);
		foreach ($signers as $signRequest) {
			if (!array_filter($this->signers, fn ($s) => $s->getId() === $signRequest->getId())) {
				$this->signers[] = $signRequest;
			}
		}
		return $signers;
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function getById(int $signRequestId): SignRequest {
		foreach ($this->signers as $signRequest) {
			if ($signRequest->getId() === $signRequestId) {
				return $signRequest;
			}
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($signRequestId, IQueryBuilder::PARAM_INT))
			);

		/** @var SignRequest */
		$signRequest = $this->findEntity($qb);
		if (!array_filter($this->signers, fn ($s) => $s->getId() === $signRequest->getId())) {
			$this->signers[] = $signRequest;
		}
		return $signRequest;
	}

	/**
	 * Get all signers by multiple fileId
	 *
	 * @return SignRequest[]
	 */
	public function getByMultipleFileId(array $fileId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName(), 'sr')
			->where(
				$qb->expr()->in('sr.file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT_ARRAY))
			)
			->orderBy('sr.id', 'ASC');

		/** @var SignRequest[] */
		return $this->findEntities($qb);
	}

	/**
	 * Get all signers by fileId
	 *
	 * @return SignRequest[]
	 */
	public function getByNodeId(int $nodeId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('sr.*')
			->from($this->getTableName(), 'sr')
			->join('sr', 'libresign_file', 'f', 'sr.file_id = f.id')
			->where(
				$qb->expr()->eq('f.node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT))
			);

		/** @var SignRequest[] */
		$signers = $this->findEntities($qb);
		return $signers;
	}

	/**
	 * Get all signers by File Uuid
	 *
	 * @param string $nodeId
	 * @return SignRequest[]
	 */
	public function getByFileUuid(string $uuid) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('sr.*')
			->from($this->getTableName(), 'sr')
			->join('sr', 'libresign_file', 'f', 'sr.file_id = f.id')
			->where(
				$qb->expr()->eq('f.uuid', $qb->createNamedParameter($uuid))
			);

		/** @var SignRequest[] */
		$signers = $this->findEntities($qb);
		foreach ($signers as $signRequest) {
			if (!array_filter($this->signers, fn ($s) => $s->getId() === $signRequest->getId())) {
				$this->signers[] = $signRequest;
			}
		}
		return $signers;
	}

	public function getBySignerUuidAndUserId(string $uuid): SignRequest {
		$qb = $this->db->getQueryBuilder();

		$qb->select('sr.*')
			->from($this->getTableName(), 'sr')
			->where(
				$qb->expr()->eq('sr.uuid', $qb->createNamedParameter($uuid))
			);

		/** @var SignRequest */
		$signRequest = $this->findEntity($qb);
		if (!array_filter($this->signers, fn ($s) => $s->getId() === $signRequest->getId())) {
			$this->signers[] = $signRequest;
		}
		return $signRequest;
	}

	public function getByFileIdAndUserId(int $file_id): SignRequest {
		$qb = $this->db->getQueryBuilder();

		$qb->select('sr.*')
			->from($this->getTableName(), 'sr')
			->join('sr', 'libresign_file', 'f', 'sr.file_id = f.id')
			->where(
				$qb->expr()->eq('f.node_id', $qb->createNamedParameter($file_id, IQueryBuilder::PARAM_INT))
			);

		/** @var SignRequest */
		return $this->findEntity($qb);
	}

	public function getByFileIdAndEmail(int $file_id, string $email): SignRequest {
		$qb = $this->db->getQueryBuilder();

		$qb->select('sr.*')
			->from($this->getTableName(), 'sr')
			->join('sr', 'libresign_file', 'f', 'sr.file_id = f.id')
			->where(
				$qb->expr()->eq('f.node_id', $qb->createNamedParameter($file_id, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('sr.email', $qb->createNamedParameter($email))
			);

		/** @var SignRequest */
		return $this->findEntity($qb);
	}

	public function getByFileIdAndSignRequestId(int $fileId, int $signRequestId): SignRequest {
		$filtered = array_filter($this->signers, fn ($e) => $e->getId() === $signRequestId);
		if ($filtered) {
			return current($filtered);
		}
		$qb = $this->db->getQueryBuilder();

		$qb->select('sr.*')
			->from($this->getTableName(), 'sr')
			->join('sr', 'libresign_file', 'f', 'sr.file_id = f.id')
			->where(
				$qb->expr()->eq('f.node_id', $qb->createNamedParameter($fileId))
			)
			->andWhere(
				$qb->expr()->eq('sr.id', $qb->createNamedParameter($signRequestId))
			);

		$signRequest = $this->findEntity($qb);
		if (!array_filter($this->signers, fn ($s) => $s->getId() === $signRequest->getId())) {
			$this->signers[] = $signRequest;
		}
		/** @var SignRequest */
		return end($this->signers);
	}

	public function getFilesAssociatedFilesWithMeFormatted(
		IUser $user,
		array $filter,
		?int $page = null,
		?int $length = null,
		?array $sort = [],
	): array {
		$filter['email'] = $user->getEMailAddress();
		$filter['length'] = $length;
		$filter['page'] = $page;
		$pagination = $this->getFilesAssociatedFilesWithMeStmt($user->getUID(), $filter, $sort);
		$pagination->setMaxPerPage($length);
		$pagination->setCurrentPage($page);
		$currentPageResults = $pagination->getCurrentPageResults();

		$data = [];
		foreach ($currentPageResults as $row) {
			$data[] = $this->formatListRow($row);
		}
		$return['data'] = $data;
		$return['pagination'] = $pagination;
		return $return;
	}

	/**
	 * @param array<SignRequest> $signRequests
	 * @return FileElement[][]
	 */
	public function getVisibleElementsFromSigners(array $signRequests): array {
		$signRequestIds = array_map(fn (SignRequest $signRequest): int => $signRequest->getId(), $signRequests);
		if (!$signRequestIds) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('fe.*')
			->from('libresign_file_element', 'fe')
			->where(
				$qb->expr()->in('fe.sign_request_id', $qb->createParameter('signRequestIds'))
			);
		$return = [];
		foreach (array_chunk($signRequestIds, 1000) as $signRequestIdsChunk) {
			$qb->setParameter('signRequestIds', $signRequestIdsChunk, IQueryBuilder::PARAM_INT_ARRAY);
			$cursor = $qb->executeQuery();
			while ($row = $cursor->fetch()) {
				$fileElement = new FileElement();
				$return[$row['sign_request_id']][] = $fileElement->fromRow($row);
			}
		}
		return $return;
	}

	/**
	 * @param array<SignRequest> $signRequests
	 * @return array<array-key, array<array-key, \OCP\AppFramework\Db\Entity&\OCA\Libresign\Db\IdentifyMethod>>
	 */
	public function getIdentifyMethodsFromSigners(array $signRequests): array {
		$signRequestIds = array_map(fn (SignRequest $signRequest): int => $signRequest->getId(), $signRequests);
		if (!$signRequestIds) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('im.*')
			->from('libresign_identify_method', 'im')
			->where(
				$qb->expr()->in('im.sign_request_id', $qb->createParameter('signRequestIds'))
			)
			->orderBy('im.mandatory', 'DESC')
			->addOrderBy('im.identified_at_date', 'ASC');

		$return = [];
		foreach (array_chunk($signRequestIds, 1000) as $signRequestIdsChunk) {
			$qb->setParameter('signRequestIds', $signRequestIdsChunk, IQueryBuilder::PARAM_INT_ARRAY);
			$cursor = $qb->executeQuery();
			while ($row = $cursor->fetch()) {
				$identifyMethod = new IdentifyMethod();
				$return[$row['sign_request_id']][$row['identifier_key']] = $identifyMethod->fromRow($row);
			}
		}
		return $return;
	}

	public function getMyLibresignFile(string $userId, ?array $filter = []): File {
		$qb = $this->getFilesAssociatedFilesWithMeQueryBuilder(
			userId: $userId,
			filter: $filter,
		);
		$cursor = $qb->executeQuery();
		$row = $cursor->fetch();
		if (!$row) {
			throw new DoesNotExistException('LibreSign file not found');
		}
		$file = new File();
		return $file->fromRow($row);
	}

	private function getFilesAssociatedFilesWithMeQueryBuilder(string $userId, array $filter = [], bool $count = false): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb->from('libresign_file', 'f')
			->leftJoin('f', 'libresign_sign_request', 'sr', 'sr.file_id = f.id')
			->leftJoin('f', 'libresign_identify_method', 'im', $qb->expr()->eq('sr.id', 'im.sign_request_id'));
		if ($count) {
			$qb->select($qb->func()->count())
				->setFirstResult(0)
				->setMaxResults(null);
		} else {
			$qb->select(
				'f.id',
				'f.node_id',
				'f.user_id',
				'f.uuid',
				'f.name',
				'f.status',
				'f.metadata',
				'f.created_at',
			)
				->groupBy(
					'f.id',
					'f.node_id',
					'f.user_id',
					'f.uuid',
					'f.name',
					'f.status',
					'f.created_at',
				);
			// metadata is a json column, the right way is to use f.metadata::text
			// when the database is PostgreSQL. The problem is that the command
			// addGroupBy add quotes over all text send as argument. With
			// PostgreSQL json columns don't have problem if not added to group by.
			if ($qb->getConnection()->getDatabaseProvider() !== IDBConnection::PLATFORM_POSTGRES) {
				$qb->addGroupBy('f.metadata');
			}
			if (isset($filter['length']) && isset($filter['page'])) {
				$qb->setFirstResult($filter['length'] * ($filter['page'] - 1));
				$qb->setMaxResults($filter['length']);
			}
		}

		$or = [
			$qb->expr()->eq('f.user_id', $qb->createNamedParameter($userId)),
			$qb->expr()->andX(
				$qb->expr()->eq('im.identifier_key', $qb->createNamedParameter(IdentifyMethodService::IDENTIFY_ACCOUNT)),
				$qb->expr()->eq('im.identifier_value', $qb->createNamedParameter($userId))
			)
		];
		$qb->where($qb->expr()->orX(...$or));
		if ($filter) {
			if (isset($filter['email']) && filter_var($filter['email'], FILTER_VALIDATE_EMAIL)) {
				$or[] = $qb->expr()->andX(
					$qb->expr()->eq('im.identifier_key', $qb->createNamedParameter(IdentifyMethodService::IDENTIFY_EMAIL)),
					$qb->expr()->eq('im.identifier_value', $qb->createNamedParameter($filter['email']))
				);
			}
			if (!empty($filter['signer_uuid'])) {
				$qb->andWhere(
					$qb->expr()->eq('sr.uuid', $qb->createNamedParameter($filter['signer_uuid']))
				);
			}
			if (!empty($filter['nodeIds'])) {
				$qb->andWhere(
					$qb->expr()->in('f.node_id', $qb->createNamedParameter($filter['nodeIds'], IQueryBuilder::PARAM_STR_ARRAY))
				);
			}
			if (!empty($filter['status'])) {
				$qb->andWhere(
					$qb->expr()->in('f.status', $qb->createNamedParameter($filter['status'], IQueryBuilder::PARAM_INT_ARRAY))
				);
			}
			if (!empty($filter['start'])) {
				$qb->andWhere(
					$qb->expr()->gte('f.created_at', $qb->createNamedParameter($filter['start'], IQueryBuilder::PARAM_INT))
				);
			}
			if (!empty($filter['end'])) {
				$qb->andWhere(
					$qb->expr()->lte('f.created_at', $qb->createNamedParameter($filter['end'], IQueryBuilder::PARAM_INT))
				);
			}
		}
		return $qb;
	}

	private function getFilesAssociatedFilesWithMeStmt(
		string $userId,
		?array $filter = [],
		?array $sort = [],
	): Pagination {
		$qb = $this->getFilesAssociatedFilesWithMeQueryBuilder($userId, $filter);
		if (!empty($sort['sortBy'])) {
			switch ($sort['sortBy']) {
				case 'name':
				case 'status':
					$qb->orderBy(
						$qb->func()->lower('f.' . $sort['sortBy']),
						$sort['sortDirection'] == 'asc' ? 'asc' : 'desc'
					);
					break;
				case 'created_at':
					$qb->orderBy(
						'f.' . $sort['sortBy'],
						$sort['sortDirection'] == 'asc' ? 'asc' : 'desc'
					);
			}
		}

		$countQb = $this->getFilesAssociatedFilesWithMeQueryBuilder(
			userId: $userId,
			filter: $filter,
			count: true,
		);

		$pagination = new Pagination($qb, $this->urlGenerator, $countQb);
		return $pagination;
	}

	private function formatListRow(array $row): array {
		$row['id'] = (int)$row['id'];
		$row['status'] = (int)$row['status'];
		$row['statusText'] = $this->fileMapper->getTextOfStatus($row['status']);
		$row['nodeId'] = (int)$row['node_id'];
		$row['requested_by'] = [
			'userId' => $row['user_id'],
			'displayName' => $this->userManager->get($row['user_id'])?->getDisplayName(),
		];
		$row['created_at'] = (new \DateTime($row['created_at']))->format(DateTimeInterface::ATOM);
		$row['file'] = $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => $row['uuid']]);
		$row['nodeId'] = (int)$row['node_id'];
		unset(
			$row['user_id'],
			$row['node_id'],
		);
		return $row;
	}
}
