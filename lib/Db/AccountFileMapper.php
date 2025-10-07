<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use DateTimeInterface;
use OCA\Libresign\Helper\Pagination;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\IURLGenerator;

/**
 * Class FileMapper
 *
 * @package OCA\Libresign\DB
 * @template-extends QBMapper<AccountFile>
 */
class AccountFileMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private IURLGenerator $urlGenerator,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private FileTypeMapper $fileTypeMapper,
	) {
		parent::__construct($db, 'libresign_account_file');
	}

	public function getByUserAndType(string $userId, string $type): AccountFile {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('file_type', $qb->createNamedParameter($type))
			);

		/** @var AccountFile */
		return $this->findEntity($qb);
	}

	public function getByUserIdAndNodeId(string $userId, int $nodeId): AccountFile {
		$qb = $this->db->getQueryBuilder();

		$qb->select('laf.*')
			->from($this->getTableName(), 'laf')
			->join('laf', 'libresign_file', 'lf', 'laf.file_id = lf.id')
			->where(
				$qb->expr()->eq('laf.user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('lf.node_id', $qb->createNamedParameter($nodeId, IQueryBuilder::PARAM_INT))
			);

		/** @var AccountFile */
		return $this->findEntity($qb);
	}

	public function getByFileId(int $fileId): AccountFile {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);

		/** @var AccountFile */
		return $this->findEntity($qb);
	}

	public function accountFileList(array $filter, ?int $page = null, ?int $length = null): array {
		$filter['length'] = $length;
		$filter['page'] = $page;
		$pagination = $this->getUserAccountFile($filter);
		$pagination->setMaxPerPage($length);
		$pagination->setCurrentPage($page);
		$currentPageResults = $pagination->getCurrentPageResults();

		$url = $this->urlGenerator->linkToRoute('libresign.page.getPdfFile', ['uuid' => '_replace_']);
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
		if ($count) {
			$qb->select($qb->func()->count())
				->setFirstResult(0)
				->setMaxResults(null);
		} else {
			$qb
				->select(
					'f.id',
					'f.uuid',
					'f.name',
					'f.callback',
					'f.status',
					'f.node_id',
					'af.file_type',
					'f.created_at',
				)
				->selectAlias('u.uid_lower', 'account_uid')
				->selectAlias('u.displayname', 'account_displayname')
				->groupBy(
					'f.id',
					'f.uuid',
					'f.name',
					'f.callback',
					'f.status',
					'f.node_id',
					'f.created_at',
					'af.file_type',
					'u.uid_lower',
					'u.displayname'
				);
			if (isset($filter['length']) && isset($filter['page'])) {
				$qb->setFirstResult($filter['length'] * ($filter['page'] - 1));
				$qb->setMaxResults($filter['length']);
			}
		}
		$qb
			->from($this->getTableName(), 'af')
			->join('af', 'libresign_file', 'f', 'f.id = af.file_id')
			->join('af', 'users', 'u', 'af.user_id = u.uid')
			->leftJoin('f', 'libresign_sign_request', 'sr', 'sr.file_id = f.id');
		if (!empty($filter['userId'])) {
			$qb->where(
				$qb->expr()->eq('af.user_id', $qb->createNamedParameter($filter['userId'])),
			);
		}
		if (!empty($filter['approved'])) {
			if ($filter['approved'] === 'yes') {
				$qb->andWhere(
					$qb->expr()->eq('f.status', $qb->createNamedParameter(File::STATUS_SIGNED, Types::INTEGER)),
				);
			}
		}
		if (!empty($filter['userId'])) {
			$qb->andWhere(
				$qb->expr()->eq('af.user_id', $qb->createNamedParameter($filter['userId'])),
			);
		}
		return $qb;
	}

	private function getUserAccountFile(array $filter = []): Pagination {
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
		$row['account'] = [
			'uid' => $row['account_uid'],
			'displayName' => $row['account_displayname']
		];
		$row['file_type'] = [
			'type' => $row['file_type'],
			'name' => $this->fileTypeMapper->getNameOfType($row['file_type']),
			'description' => $this->fileTypeMapper->getDescriptionOfType($row['file_type']),
		];
		$row['created_at'] = $row['created_at']->format(DateTimeInterface::ATOM);
		$row['file'] = [
			'name' => $row['name'],
			'status' => $row['status'],
			'statusText' => $this->fileMapper->getTextOfStatus((int)$row['status']),
			'created_at' => $row['created_at'],
			'file' => [
				'type' => 'pdf',
				'nodeId' => (int)$row['node_id'],
				'url' => $url . $row['uuid'],
			],
			'callback' => $row['callback'],
			'uuid' => $row['uuid'],
		];
		unset(
			$row['node_id'],
			$row['name'],
			$row['status'],
			$row['created_at'],
			$row['account_displayname'],
			$row['account_uid'],
			$row['callback'],
			$row['uuid'],
			$row['account_uid'],
		);
		return $row;
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
						'request_sign_date' => $signer->getCreatedAt()->format(DateTimeInterface::ATOM),
						'sign_date' => $signer->getSigned(),
						'signRequestId' => $signer->getId(),
					];
					if ($data['sign_date']) {
						$data['sign_date'] = $data['sign_date']->format(DateTimeInterface::ATOM);
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

	#[\Override]
	public function delete(Entity $entity): Entity {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->tableName)
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($entity->getUserId(), Types::STRING)),
				$qb->expr()->eq('file_id', $qb->createNamedParameter($entity->getFileId(), Types::INTEGER))
			);
		$qb->executeStatement();
		$qb->resetQueryParts();
		$qb->delete('libresign_file')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($entity->getFileId(), Types::INTEGER))
			);
		$qb->executeStatement();
		return $entity;
	}
}
