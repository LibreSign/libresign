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

	public function findBySerialNumber(int $serialNumber): Crl {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('serial_number', $qb->createNamedParameter($serialNumber, IQueryBuilder::PARAM_INT)));

		/** @var Crl */
		return $this->findEntity($qb);
	}

	public function createCertificate(
		int $serialNumber,
		string $owner,
		DateTime $issuedAt,
		?DateTime $validTo = null,
	): Crl {
		$certificate = new Crl();
		$certificate->setSerialNumber($serialNumber);
		$certificate->setOwner($owner);
		$certificate->setStatus(CRLStatus::ISSUED);
		$certificate->setIssuedAt($issuedAt);
		$certificate->setValidTo($validTo);

		/** @var Crl */
		return $this->insert($certificate);
	}

	public function revokeCertificate(
		int $serialNumber,
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
		$certificate->setComment($comment);
		$certificate->setRevokedBy($revokedBy);
		$certificate->setRevokedAt(new DateTime());
		$certificate->setInvalidityDate($invalidityDate);
		$certificate->setCrlNumber($crlNumber);

		/** @var Crl */
		return $this->update($certificate);
	}

	public function getRevokedCertificates(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter(CRLStatus::REVOKED->value)))
			->orderBy('revoked_at', 'DESC');

		return $this->findEntities($qb);
	}

	public function isInvalidAt(int $serialNumber, ?DateTime $checkDate = null): bool {
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

	public function getNextCrlNumber(): int {
		$qb = $this->db->getQueryBuilder();

		$result = $qb->select($qb->func()->max('crl_number'))
			->from($this->getTableName())
			->executeQuery();

		$maxCrlNumber = $result->fetchOne();
		$result->closeCursor();

		return ($maxCrlNumber ?? 0) + 1;
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
}
