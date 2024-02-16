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
 */
class AccountFileMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private IURLGenerator $urlGenerator,
		private FileMapper $fileMapper,
		private SignRequestMapper $signRequestMapper,
		private FileTypeMapper $fileTypeMapper
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

		return $this->findEntity($qb);
	}

	public function getByFileId(int $fileId): AccountFile {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @return array<\OCA\Libresign\Helper\Pagination|array>
	 * @psalm-return array{pagination: \OCA\Libresign\Helper\Pagination, data: array}
	 */
	public function accountFileList(array $filter, int $page = null, int $length = null): array {
		$pagination = $this->getUserAccountFile($filter);
		$pagination->setMaxPerPage($length);
		$pagination->setCurrentPage($page);
		$currentPageResults = $pagination->getCurrentPageResults();

		$url = $this->urlGenerator->linkToRoute('libresign.page.getPdfUser', ['uuid' => '_replace_']);
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

	private function getUserAccountFile(array $filter = [], bool $count = false): Pagination {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'f.id',
			'f.uuid',
			'f.name',
			'f.callback',
			'f.status',
			'f.node_id',
			'af.file_type'
		)
			->selectAlias('u.uid_lower', 'account_uid')
			->selectAlias('u.displayname', 'account_displayname')
			->selectAlias('f.created_at', 'request_date')
			->selectAlias($qb->func()->max('sr.signed'), 'status_date')
			->from($this->getTableName(), 'af')
			->join('af', 'libresign_file', 'f', 'f.id = af.file_id')
			->join('af', 'users', 'u', 'af.user_id = u.uid')
			->leftJoin('f', 'libresign_sign_request', 'sr', 'sr.file_id = f.id')
			->groupBy(
				'f.id',
				'f.uuid',
				'f.name',
				'f.callback',
				'f.node_id',
				'f.created_at',
				'af.file_type',
				'u.uid_lower',
				'u.displayname'
			);
		if (!empty($filter['userId'])) {
			$qb->where(
				$qb->expr()->eq('af.user_id', $qb->createNamedParameter($filter['userId'])),
			);
		}

		$countQueryBuilderModifier = function (IQueryBuilder &$qb) use ($filter): void {
			$count = $qb->getConnection()->getQueryBuilder();
			$count->selectAlias($count->func()->count(), 'total_results')
				->from($this->getTableName(), 'af')
				->setMaxResults(1);
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
			$qb = $count;
		};

		$pagination = new Pagination($qb, $countQueryBuilderModifier);
		return $pagination;
	}

	/**
	 * @return (((int|mixed|string)[]|false|mixed|null|string)[]|mixed)[]
	 *
	 * @psalm-return array{file: array{name: mixed, status: mixed, statusText: null|string, status_date: false|mixed|string, request_date: false|string, requested_by: array{displayName: mixed, uid: mixed}, file: array{type: 'pdf', nodeId: int, url: string}, callback: mixed, uuid: mixed}}
	 */
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
		$row['request_date'] = (new \DateTime())
			->setTimestamp((int) $row['request_date'])
			->format('Y-m-d H:i:s');
		if (!empty($row['status_date'])) {
			$row['status_date'] = (new \DateTime())
				->setTimestamp((int) $row['status_date'])
				->format('Y-m-d H:i:s');
		}
		$row['file'] = [
			'name' => $row['name'],
			'status' => $row['status'],
			'statusText' => $this->fileMapper->getTextOfStatus((int) $row['status']),
			'status_date' => $row['status_date'],
			'request_date' => $row['request_date'],
			'requested_by' => [
				'displayName' => $row['account_displayname'],
				'uid' => $row['account_uid'],
			],
			'file' => [
				'type' => 'pdf',
				'nodeId' => (int) $row['node_id'],
				'url' => $url . $row['uuid'],
			],
			'callback' => $row['callback'],
			'uuid' => $row['uuid'],
		];
		unset(
			$row['node_id'],
			$row['name'],
			$row['status'],
			$row['status_date'],
			$row['request_date'],
			$row['account_displayname'],
			$row['account_uid'],
			$row['callback'],
			$row['uuid'],
			$row['account_uid'],
			$row['account_displayname']
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
						'email' => $signer->getEmail(),
						'description' => $signer->getDescription(),
						'displayName' => $signer->getDisplayName(),
						'request_sign_date' => (new \DateTime())
							->setTimestamp($signer->getCreatedAt())
							->format('Y-m-d H:i:s'),
						'sign_date' => null,
						'uid' => $signer->getUserId(),
						'signRequestId' => $signer->getId(),
						'identifyMethod' => $signer->getIdentifyMethod(),
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
