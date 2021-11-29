<?php

namespace OCA\Libresign\Db;

use OCA\Libresign\Helper\Pagination;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
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
	/** @var FileUserMapper */
	private $fileUserMapper;
	/**
	 * @param IDBConnection $db
	 */
	public function __construct(
		IDBConnection $db,
		IURLGenerator $urlGenerator,
		FileUserMapper $fileUserMapper
	) {
		parent::__construct($db, 'libresign_account_file');
		$this->urlGenerator = $urlGenerator;
		$this->fileUserMapper = $fileUserMapper;
	}

	public function getByUserAndType(string $userId, string $type): AccountFile {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('file_type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_STR))
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
				'f.node_id'
			)
			->selectAlias('u.uid_lower', 'account_uid')
			->selectAlias('u.displayname', 'account_dislpayname')
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
				'u.uid_lower',
				'u.displayname'
			);
		if (!empty($filter['userId'])) {
			$qb->where(
				$qb->expr()->eq('af.user_id', $qb->createNamedParameter($filter['userId'], IQueryBuilder::PARAM_STR)),
			);
		}

		$countQueryBuilderModifier = function (IQueryBuilder &$qb) use ($filter): void {
			$count = $qb->getConnection()->getQueryBuilder();
			$count->selectAlias($count->func()->count(), 'total_results')
				->from($this->getTableName(), 'af')
				->setMaxResults(1);
			if (!empty($filter['userId'])) {
				$qb->where(
					$qb->expr()->eq('af.user_id', $qb->createNamedParameter($filter['userId'], IQueryBuilder::PARAM_STR)),
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
		$row['id'] = (int) $row['id'];
		$row['account'] = [
			'uid' => $row['account_uid'],
			'displayName' => $row['account_dislpayname']
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
			$row['account_uid'],
			$row['account_dislpayname']
		);
		return $row;
	}

	private function assocFileToFileUserAndFormat(array $files, array $signers): array {
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
						'fileUserId' => $signer->getId()
					];
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
				$files[$key]['status'] = 'no signers';
			} elseif ($totalSigned === count($files[$key]['signers'])) {
				$files[$key]['status'] = 'signed';
			} else {
				$files[$key]['status'] = 'pending';
			}
			unset($files[$key]['id']);
		}
		return $files;
	}
}
