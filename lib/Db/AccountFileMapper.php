<?php

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
 *
 * @codeCoverageIgnore
 * @method File insert(File $entity)
 * @method File update(File $entity)
 * @method File insertOrUpdate(File $entity)
 * @method File delete(File $entity)
 */
class AccountFileMapper extends QBMapper {
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var FileMapper */
	private $fileMapper;
	/** @var FileUserMapper */
	private $fileUserMapper;
	/** @var FileTypeMapper */
	private $fileTypeMapper;
	/**
	 * @param IDBConnection $db
	 */
	public function __construct(
		IDBConnection $db,
		IURLGenerator $urlGenerator,
		FileMapper $fileMapper,
		FileUserMapper $fileUserMapper,
		FileTypeMapper $fileTypeMapper
	) {
		parent::__construct($db, 'libresign_account_file');
		$this->urlGenerator = $urlGenerator;
		$this->fileMapper = $fileMapper;
		$this->fileUserMapper = $fileUserMapper;
		$this->fileTypeMapper = $fileTypeMapper;
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

	public function getByFileId($fileId): AccountFile {
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
		$signers = $this->fileUserMapper->getByMultipleFileId($fileIds);
		$return['data'] = $this->assocFileToFileUserAndFormat($data, $signers);
		$return['pagination'] = $pagination;
		return $return;
	}

	/**
	 * @return Pagination
	 */
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
			->selectAlias($qb->func()->max('fu.signed'), 'status_date')
			->from($this->getTableName(), 'af')
			->join('af', 'libresign_file', 'f', 'f.id = af.file_id')
			->join('af', 'users', 'u', 'af.user_id = u.uid')
			->leftJoin('f', 'libresign_file_user', 'fu', 'fu.file_id = f.id')
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
			if (!empty($filter['userId'])) {
				$qb->where(
					$qb->expr()->eq('af.user_id', $qb->createNamedParameter($filter['userId'])),
				);
			}
			$qb = $count;
		};

		$pagination = new Pagination($qb, $countQueryBuilderModifier);
		return $pagination;
	}

	/**
	 * @return ((int|string)[]|mixed|string)[]
	 *
	 * @psalm-return array{status_date: string, file: array{type: 'pdf', url: string, nodeId: int}}
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
			->setTimestamp($row['request_date'])
			->format('Y-m-d H:i:s');
		if (!empty($row['status_date'])) {
			$row['status_date'] = (new \DateTime())
				->setTimestamp($row['status_date'])
				->format('Y-m-d H:i:s');
		}
		$row['file'] = [
			'name' => $row['name'],
			'status' => $row['status'],
			'status_text' => $this->fileMapper->getTextOfStatus($row['status']),
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

	private function assocFileToFileUserAndFormat(array $files, array $signers): array {
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
						'fileUserId' => $signer->getId()
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
		$qb->execute();
		$qb->resetQueryParts();
		$qb->delete('libresign_file')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($entity->getFileId(), Types::INTEGER))
			);
		$qb->execute();
		return $entity;
	}
}
