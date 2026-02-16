<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCA\Libresign\Enum\FileStatus;
use OCA\Libresign\Helper\Pagination;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\DB
 * @template-extends QBMapper<IdDocs>
 */
class IdDocsMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private IURLGenerator $urlGenerator,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private FileTypeMapper $fileTypeMapper,
		private IL10N $l10n,
	) {
		parent::__construct($db, 'libresign_id_docs');
	}

	public function save(int $fileId, ?int $signRequestId, ?string $userId, string $fileType): IdDocs {
		$idDocs = new IdDocs();
		$idDocs->setFileId($fileId);
		$idDocs->setSignRequestId($signRequestId);
		$idDocs->setUserId($userId);
		$idDocs->setFileType($fileType);
		return $this->insert($idDocs);
	}

	public function getByUserIdAndFileId(string $userId, int $fileId): IdDocs {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @return IdDocs[]
	 */
	public function getByUserId(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntities($qb);
	}

	public function getByUserIdAndNodeId(string $userId, int $nodeId): IdDocs {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id.*')
			->from($this->getTableName(), 'id')
			->join('id', 'libresign_file', 'f', 'f.id = id.file_id')
			->where($qb->expr()->eq('id.user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('f.node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function getByNodeId(int $nodeId): IdDocs {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id.*')
			->from($this->getTableName(), 'id')
			->join('id', 'libresign_file', 'f', 'f.id = id.file_id')
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('f.node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)),
					$qb->expr()->eq('f.signed_node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT))
				)
			);
		return $this->findEntity($qb);
	}

	public function getBySignRequestIdAndNodeId(int $signRequestId, int $nodeId): IdDocs {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id.*')
			->from($this->getTableName(), 'id')
			->join('id', 'libresign_file', 'f', 'f.id = id.file_id')
			->where($qb->expr()->eq('id.sign_request_id', $qb->createNamedParameter($signRequestId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('f.node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function getByFileId(int $fileId): IdDocs {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function deleteByFileId(int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function getByUserAndType(string $userId, string $fileType): ?IdDocs {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('file_type', $qb->createNamedParameter($fileType)));
		try {
			return $this->findEntity($qb);
		} catch (\Throwable) {
			return null;
		}
	}

	public function list(array $filter, ?int $page = null, ?int $length = null, array $sort = []): array {
		$filter['length'] = $length;
		$filter['page'] = $page;
		$filter['sort'] = $sort;
		$pagination = $this->getDocs($filter);
		$pagination->setMaxPerPage($length);
		$pagination->setCurrentPage($page);
		$currentPageResults = $pagination->getCurrentPageResults();

		$url = $this->urlGenerator->linkToRoute('libresign.page.getPdf', ['uuid' => '_replace_']);
		$url = str_replace('_replace_', '', $url);

		$data = [];
		$fileIds = [];

		foreach ($currentPageResults as $row) {
			$fileIds[] = $row['id'];
			$data[] = $this->formatListRow($row, $url);
		}
		$signers = $this->signRequestMapper->getByMultipleFileId($fileIds);
		$return['data'] = $this->assocFileToSignRequestAndFormat($data, $signers);
		$return['pagination'] = $pagination;
		return $return;
	}

	private function getQueryBuilder(array $filter = [], bool $count = false): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();

		$needsUserJoin = !$count || !empty($filter['userId']) || (!empty($filter['sort']) && isset($filter['sort']['owner']));

		if ($count) {
			$qb->select($qb->func()->count());
		} else {
			$qb->select(
				'f.id',
				'f.uuid',
				'f.name',
				'f.callback',
				'f.status',
				'f.node_id',
				'f.user_id',
				'id.file_type',
				'id.sign_request_id',
				'f.created_at',
			);

			$qb->selectAlias('sr.display_name', 'sign_request_display_name');

			if ($needsUserJoin) {
				$qb->selectAlias('u.uid_lower', 'account_uid')
					->selectAlias('u.displayname', 'account_displayname');
			}

			$qb->selectAlias(
				$qb->createFunction(
					'CASE WHEN u.displayname IS NOT NULL THEN u.displayname ELSE sr.display_name END'
				),
				'owner_display_name'
			);
		}

		$qb->from($this->getTableName(), 'id')
			->join('id', 'libresign_file', 'f', 'f.id = id.file_id')
			->leftJoin('id', 'libresign_sign_request', 'sr', 'id.sign_request_id = sr.id');

		if ($needsUserJoin) {
			$joinType = !empty($filter['userId']) ? 'join' : 'leftJoin';
			$qb->$joinType('id', 'users', 'u', 'id.user_id = u.uid');
		}

		if (!empty($filter['userId'])) {
			$qb->where(
				$qb->expr()->eq('id.user_id', $qb->createNamedParameter($filter['userId']))
			);
		}

		if (!empty($filter['signRequestId'])) {
			$qb->andWhere(
				$qb->expr()->eq('id.sign_request_id', $qb->createNamedParameter($filter['signRequestId'], IQueryBuilder::PARAM_INT))
			);
		}

		if (!empty($filter['approved']) && $filter['approved'] === 'yes') {
			$qb->andWhere(
				$qb->expr()->eq('f.status', $qb->createNamedParameter(FileStatus::SIGNED->value, Types::INTEGER))
			);
		}

		if (!$count) {
			$qb->groupBy(
				'f.id',
				'f.uuid',
				'f.name',
				'f.callback',
				'f.status',
				'f.node_id',
				'f.user_id',
				'id.sign_request_id',
				'sr.display_name',
				'f.created_at',
				'id.file_type',
			);

			if ($needsUserJoin) {
				$qb->addGroupBy('u.uid_lower')
					->addGroupBy('u.displayname');
			}

			$qb->addGroupBy('owner_display_name');
		}

		if (!$count) {
			if (!empty($filter['sort'])) {
				foreach ($filter['sort'] as $field => $direction) {
					$direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

					switch ($field) {
						case 'owner':
							$qb->addOrderBy('owner_display_name', $direction);
							break;
						case 'file_type':
							$qb->addOrderBy('id.file_type', $direction);
							break;
						case 'status':
							$qb->addOrderBy('f.status', $direction);
							break;
						case 'created_at':
							$qb->addOrderBy('f.created_at', $direction);
							break;
					}
				}
			} else {
				$qb->orderBy('f.created_at', 'DESC');
			}
		}

		if ($count) {
			$qb->setFirstResult(0)->setMaxResults(null);
		} elseif (isset($filter['length'], $filter['page'])) {
			$qb->setFirstResult($filter['length'] * ($filter['page'] - 1))
				->setMaxResults($filter['length']);
		}

		return $qb;
	}

	private function getDocs(array $filter = []): Pagination {
		$qb = $this->getQueryBuilder(
			filter: $filter,
		);
		$countQb = $this->getQueryBuilder(
			filter: $filter,
			count: true,
		);

		$pagination = new Pagination($qb, $this->urlGenerator, $countQb);
		return $pagination;
	}

	private function formatListRow(array $row, string $url): array {
		$createdAt = (new \DateTime())
			->setTimestamp((int)$row['created_at'])
			->format('Y-m-d H:i:s');

		$userId = $row['user_id'] ?? null;
		$displayName = $row['account_displayname'] ?? $row['sign_request_display_name'] ?? $userId ?? '';

		return [
			'account' => [
				'userId' => $userId,
				'displayName' => $displayName,
			],
			'file_type' => [
				'type' => $row['file_type'],
				'name' => $this->fileTypeMapper->getNameOfType($row['file_type']),
				'description' => $this->fileTypeMapper->getDescriptionOfType($row['file_type']),
			],
			'created_at' => $createdAt,
			'file' => [
				'name' => $row['name'],
				'status' => (int)$row['status'],
				'statusText' => $this->getIdDocStatusText((int)$row['status']),
				'created_at' => $createdAt,
				'user_id' => $row['user_id'],
				'file' => [
					'type' => 'pdf',
					'nodeId' => (int)$row['node_id'],
					'signedNodeId' => (int)$row['node_id'],
					'url' => $url . $row['uuid'],
				],
				'callback' => $row['callback'],
				'uuid' => $row['uuid'],
				'signers' => [],
			],
			'id' => (int)$row['id'],
		];
	}

	/**
	 * @param array $files
	 * @param SignRequest[] $signers
	 */
	private function assocFileToSignRequestAndFormat(array $files, array $signers): array {
		foreach ($files as $key => $file) {
			$totalSigned = 0;
			$files[$key]['file']['signers'] = [];
			foreach ($signers as $signerKey => $signer) {
				if ($signer->getFileId() === $file['id']) {
					$data = [
						'description' => $signer->getDescription(),
						'displayName' => $signer->getDisplayName(),
						'uid' => $file['file']['user_id'] ?? null,
						'request_sign_date' => (new \DateTime())
							->setTimestamp($signer->getCreatedAt()->getTimestamp())
							->format('Y-m-d H:i:s'),
						'sign_date' => null,
						'signRequestId' => $signer->getId(),
						'status' => $signer->getStatus(),
						'statusText' => $this->signRequestMapper->getTextOfSignerStatus($signer->getStatus()),
					];
					if ($signer->getSigned()) {
						$data['sign_date'] = (new \DateTime())
							->setTimestamp($signer->getSigned()->getTimestamp())
							->format('Y-m-d H:i:s');
						$totalSigned++;
					}
					$files[$key]['file']['signers'][] = $data;
					unset($signers[$signerKey]);
				}
			}
			unset($files[$key]['id']);
		}
		return $files;
	}

	/**
	 * Get all identification document files for a specific user account
	 *
	 * @param string $userId The user identifier
	 * @return File[] Array of File entities associated with the user's identification documents
	 */
	public function getFilesOfAccount(string $userId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('lf.*')
			->from('libresign_file', 'lf')
			->join('lf', $this->getTableName(), 'lid', 'lid.file_id = lf.id')
			->where(
				$qb->expr()->eq('lid.user_id', $qb->createNamedParameter($userId))
			);

		$cursor = $qb->executeQuery();
		$return = [];
		while ($row = $cursor->fetch()) {
			/** @var File */
			$file = $this->fileMapper->mapRowToEntity($row);
			$return[] = $file;
		}
		return $return;
	}

	/**
	 * Get all identification document files for a specific sign request
	 *
	 * @param int $signRequestId The sign request identifier
	 * @return File[] Array of File entities associated with the sign request's identification documents
	 */
	public function getFilesOfSignRequest(int $signRequestId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('lf.*')
			->from('libresign_file', 'lf')
			->join('lf', $this->getTableName(), 'lid', 'lid.file_id = lf.id')
			->where(
				$qb->expr()->eq('lid.sign_request_id', $qb->createNamedParameter($signRequestId, IQueryBuilder::PARAM_INT))
			);

		$cursor = $qb->executeQuery();
		$return = [];
		while ($row = $cursor->fetch()) {
			/** @var File */
			$file = $this->fileMapper->mapRowToEntity($row);
			$return[] = $file;
		}
		return $return;
	}

	private function getIdDocStatusText(int $status): string {
		return match ($status) {
			FileStatus::ABLE_TO_SIGN->value => $this->l10n->t('waiting for approval'),
			FileStatus::SIGNED->value => $this->l10n->t('approved'),
			default => $this->fileMapper->getTextOfStatus($status),
		};
	}
}
