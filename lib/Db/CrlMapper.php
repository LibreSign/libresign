<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use DateTime;
use OCA\Libresign\Enum\CRLReason;
use OCA\Libresign\Enum\CRLStatus;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Crl>
 */
class CrlMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_crl');
	}

	public function findBySerialNumber(string $serialNumber): Crl {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('serial_number', $qb->createNamedParameter($serialNumber)));

		/** @var Crl */
		return $this->findEntity($qb);
	}

	/**
	 * Find all issued (non-revoked) certificates owned by a user
	 *
	 * @param string $owner User ID
	 * @return array<Crl>
	 */
	public function findIssuedByOwner(string $owner): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($owner)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(CRLStatus::ISSUED->value)));

		return $this->findEntities($qb);
	}

	public function createCertificate(
		string $serialNumber,
		string $owner,
		string $engine,
		string $instanceId,
		int $generation,
		DateTime $issuedAt,
		?DateTime $validTo = null,
	): Crl {
		$certificate = new Crl();
		$certificate->setSerialNumber($serialNumber);
		$certificate->setOwner($owner);
		$certificate->setStatus(CRLStatus::ISSUED);
		$certificate->setIssuedAt($issuedAt);
		$certificate->setValidTo($validTo);
		$certificate->setEngine($engine);
		$certificate->setInstanceId($instanceId);
		$certificate->setGeneration($generation);

		/** @var Crl */
		return $this->insert($certificate);
	}

	public function revokeCertificate(
		string $serialNumber,
		CRLReason $reason = CRLReason::UNSPECIFIED,
		?string $comment = null,
		?string $revokedBy = null,
		?DateTime $invalidityDate = null,
		?int $crlNumber = null,
	): Crl {
		$certificate = $this->findBySerialNumber($serialNumber);

		if (CRLStatus::from($certificate->getStatus()) !== CRLStatus::ISSUED) {
			throw new \InvalidArgumentException('Certificate is not in issued status');
		}

		$certificate->setStatus(CRLStatus::REVOKED);
		$certificate->setReasonCode($reason->value);
		$certificate->setComment($comment !== '' ? $comment : null);
		$certificate->setRevokedBy($revokedBy);
		$certificate->setRevokedAt(new DateTime());
		$certificate->setInvalidityDate($invalidityDate);
		$certificate->setCrlNumber($crlNumber);

		/** @var Crl */
		return $this->update($certificate);
	}

	public function getRevokedCertificates(string $instanceId = '', int $generation = 0, string $engineType = ''): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter(CRLStatus::REVOKED->value)))
			->orderBy('revoked_at', 'DESC');

		if ($instanceId !== '') {
			$qb->andWhere($qb->expr()->eq('instance_id', $qb->createNamedParameter($instanceId)));
		}
		if ($generation !== 0) {
			$qb->andWhere($qb->expr()->eq('generation', $qb->createNamedParameter($generation, IQueryBuilder::PARAM_INT)));
		}
		if ($engineType !== '') {
			$engineName = match($engineType) {
				'o' => 'openssl',
				'c' => 'cfssl',
				'openssl', 'cfssl' => $engineType,
				default => throw new \InvalidArgumentException("Invalid engine type: $engineType"),
			};
			$qb->andWhere($qb->expr()->eq('engine', $qb->createNamedParameter($engineName)));
		}

		return $this->findEntities($qb);
	}

	public function isInvalidAt(string $serialNumber, ?DateTime $checkDate = null): bool {
		$checkDate = $checkDate ?? new DateTime();

		try {
			$certificate = $this->findBySerialNumber($serialNumber);
		} catch (DoesNotExistException $e) {
			return false;
		}

		if ($certificate->isRevoked()) {
			return true;
		}

		if ($certificate->getInvalidityDate() && $certificate->getInvalidityDate() <= $checkDate) {
			return true;
		}

		return false;
	}

	public function cleanupExpiredCertificates(?DateTime $before = null): int {
		$before = $before ?? new DateTime('-1 year');

		$qb = $this->db->getQueryBuilder();

		return $qb->delete($this->getTableName())
			->where($qb->expr()->isNotNull('valid_to'))
			->andWhere($qb->expr()->lt('valid_to', $qb->createNamedParameter($before, 'datetime')))
			->executeStatement();
	}

	public function getStatistics(): array {
		$qb = $this->db->getQueryBuilder();

		$result = $qb->select('status', $qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->groupBy('status')
			->executeQuery();

		$stats = [];
		while ($row = $result->fetch()) {
			$stats[$row['status']] = (int)$row['count'];
		}

		$result->closeCursor();
		return $stats;
	}

	public function getRevocationStatistics(): array {
		$qb = $this->db->getQueryBuilder();

		$result = $qb->select('reason_code', $qb->func()->count('*', 'count'))
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter(CRLStatus::REVOKED->value)))
			->andWhere($qb->expr()->isNotNull('reason_code'))
			->groupBy('reason_code')
			->executeQuery();

		$stats = [];
		while ($row = $result->fetch()) {
			$reasonCode = (int)$row['reason_code'];
			$reason = CRLReason::tryFrom($reasonCode);
			$stats[$reasonCode] = [
				'code' => $reasonCode,
				'description' => $reason?->getDescription() ?? 'unknown',
				'count' => (int)$row['count'],
			];
		}

		$result->closeCursor();
		return $stats;
	}

	public function getLastCrlNumber(string $instanceId, int $generation, string $engineType): int {
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->func()->max('crl_number'))
			->from($this->getTableName())
			->where($qb->expr()->eq('instance_id', $qb->createNamedParameter($instanceId)))
			->andWhere($qb->expr()->eq('generation', $qb->createNamedParameter($generation, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('engine', $qb->createNamedParameter($engineType)))
			->andWhere($qb->expr()->isNotNull('crl_number'));

		$result = $qb->executeQuery();
		$maxCrlNumber = $result->fetchOne();
		$result->closeCursor();

		return (int)($maxCrlNumber ?? 0);
	}

	/**
	 * List CRL entries with pagination and filters
	 *
	 * @param int $page Page number (1-based)
	 * @param int $length Number of items per page
	 * @param array<string, mixed> $filter Filters to apply (status, engine, instance_id, owner, etc.)
	 * @param array<string, string> $sort Sort fields and directions ['field' => 'ASC|DESC']
	 * @return array{data: array<Crl>, total: int}
	 */
	public function listWithPagination(
		int $page = 1,
		int $length = 100,
		array $filter = [],
		array $sort = [],
	): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());

		if (!empty($filter['status'])) {
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($filter['status'])));
		}

		if (!empty($filter['engine'])) {
			$qb->andWhere($qb->expr()->eq('engine', $qb->createNamedParameter($filter['engine'])));
		}

		if (!empty($filter['instance_id'])) {
			$qb->andWhere($qb->expr()->eq('instance_id', $qb->createNamedParameter($filter['instance_id'])));
		}

		if (!empty($filter['generation'])) {
			$qb->andWhere($qb->expr()->eq('generation', $qb->createNamedParameter((int)$filter['generation'], IQueryBuilder::PARAM_INT)));
		}

		if (!empty($filter['owner'])) {
			$qb->andWhere($qb->expr()->like('owner', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($filter['owner']) . '%')));
		}

		if (!empty($filter['serial_number'])) {
			$qb->andWhere($qb->expr()->like('serial_number', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($filter['serial_number']) . '%')));
		}

		if (!empty($filter['revoked_by'])) {
			$qb->andWhere($qb->expr()->like('revoked_by', $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($filter['revoked_by']) . '%')));
		}

		$countQb = $this->db->getQueryBuilder();
		$countQb->select($countQb->func()->count('*', 'count'))
			->from($this->getTableName());

		if (!empty($filter['status'])) {
			$countQb->andWhere($countQb->expr()->eq('status', $countQb->createNamedParameter($filter['status'])));
		}
		if (!empty($filter['engine'])) {
			$countQb->andWhere($countQb->expr()->eq('engine', $countQb->createNamedParameter($filter['engine'])));
		}
		if (!empty($filter['instance_id'])) {
			$countQb->andWhere($countQb->expr()->eq('instance_id', $countQb->createNamedParameter($filter['instance_id'])));
		}
		if (!empty($filter['generation'])) {
			$countQb->andWhere($countQb->expr()->eq('generation', $countQb->createNamedParameter((int)$filter['generation'], IQueryBuilder::PARAM_INT)));
		}
		if (!empty($filter['owner'])) {
			$countQb->andWhere($countQb->expr()->like('owner', $countQb->createNamedParameter('%' . $this->db->escapeLikeParameter($filter['owner']) . '%')));
		}
		if (!empty($filter['serial_number'])) {
			$countQb->andWhere($countQb->expr()->like('serial_number', $countQb->createNamedParameter('%' . $this->db->escapeLikeParameter($filter['serial_number']) . '%')));
		}
		if (!empty($filter['revoked_by'])) {
			$countQb->andWhere($countQb->expr()->like('revoked_by', $countQb->createNamedParameter('%' . $this->db->escapeLikeParameter($filter['revoked_by']) . '%')));
		}

		$total = (int)$countQb->executeQuery()->fetchOne();

		$allowedSortFields = [
			'serial_number',
			'owner',
			'status',
			'engine',
			'issued_at',
			'valid_to',
			'revoked_at',
			'reason_code',
		];

		if (!empty($sort)) {
			foreach ($sort as $field => $direction) {
				if (!in_array($field, $allowedSortFields, true)) {
					continue;
				}
				$direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
				$qb->addOrderBy($field, $direction);
			}
		} else {
			$qb->orderBy('revoked_at', 'DESC')
				->addOrderBy('issued_at', 'DESC');
		}

		$offset = ($page - 1) * $length;
		$qb->setFirstResult($offset)
			->setMaxResults($length);

		return [
			'data' => $this->findEntities($qb),
			'total' => $total,
		];
	}
}
