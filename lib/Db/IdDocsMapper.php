<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCA\Libresign\Helper\Pagination;
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
class IdDocsMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private IURLGenerator $urlGenerator,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private FileTypeMapper $fileTypeMapper,
	) {
		parent::__construct($db, 'libresign_id_docs');
	}

	public function list(array $filter, ?int $page = null, ?int $length = null): array {
		$filter['length'] = $length;
		$filter['page'] = $page;
		$pagination = $this->getDocs($filter);
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
					'id.file_type',
					'f.created_at',
				)
				->groupBy(
					'f.id',
					'f.uuid',
					'f.name',
					'f.callback',
					'f.status',
					'f.node_id',
					'f.created_at',
					'id.file_type',
				);
			if (isset($filter['length']) && isset($filter['page'])) {
				$qb->setFirstResult($filter['length'] * ($filter['page'] - 1));
				$qb->setMaxResults($filter['length']);
			}
		}
		$qb
			->from($this->getTableName(), 'id')
			->join('id', 'libresign_file', 'f', 'f.id = id.file_id');
		if (empty($filter['singRequestId'])) {
			if (!$count) {
				$qb->addSelect('im.identifier_key')
					->addSelect('im.identifier_value')
					->addGroupBy('im.identifier_key')
					->addGroupBy('im.identifier_value');
			}
			$qb
				->join('f', 'libresign_sign_request', 'sr', 'sr.file_id = f.id')
				->join('sr', 'libresign_identify_method', 'im', 'im.sign_request_id = sr.id')
				->where(
					$qb->expr()->eq('sr.id', $qb->createNamedParameter($filter['singRequestId'])),
				);
		}
		if (!empty($filter['userId'])) {
			if (!$count) {
				$qb->selectAlias('u.uid_lower', 'account_uid')
					->selectAlias('u.displayname', 'account_displayname')
					->addGroupBy('u.uid_lower')
					->addGroupBy('u.displayname');
			}
			$qb
				->join('id', 'users', 'u', 'id.user_id = u.uid')
				->where(
					$qb->expr()->eq('id.user_id', $qb->createNamedParameter($filter['userId'])),
				);
		}
		if (!empty($filter['approved'])) {
			if ($filter['approved'] === 'yes') {
				$qb->andWhere(
					$qb->expr()->eq('f.status', $qb->createNamedParameter(File::STATUS_SIGNED, Types::INTEGER)),
				);
			}
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
		$row['account'] = [
			'uid' => $row['account_uid'],
			'displayName' => $row['account_displayname']
		];
		$row['file_type'] = [
			'type' => $row['file_type'],
			'name' => $this->fileTypeMapper->getNameOfType($row['file_type']),
			'description' => $this->fileTypeMapper->getDescriptionOfType($row['file_type']),
		];
		$row['created_at'] = (new \DateTime())
			->setTimestamp((int)$row['created_at'])
			->format('Y-m-d H:i:s');
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
						'request_sign_date' => (new \DateTime())
							->setTimestamp($signer->getCreatedAt())
							->format('Y-m-d H:i:s'),
						'sign_date' => null,
						'signRequestId' => $signer->getId(),
					];
					if ($signer->getSigned()) {
						$data['sign_date'] = (new \DateTime())
							->setTimestamp($signer->getSigned())
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
}
