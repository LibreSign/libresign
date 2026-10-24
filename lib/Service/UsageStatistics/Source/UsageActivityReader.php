<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\UsageStatistics\Source;

use OCA\Libresign\Enum\CertificateType;
use OCA\Libresign\Enum\NodeType;
use OCA\Libresign\Enum\ParticipantRole;
use OCA\Libresign\Service\UsageStatistics\Model\ReportPeriod;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Aggregate counts of stored LibreSign events, by the timestamp of each event.
 *
 * Signing activity is counted on top-level files only: an envelope and each
 * of its files carry their own signing request for the same signer, so
 * counting the envelope files too would count one signer several times.
 * Identification documents are LibreSign files too, but not signing requests.
 */
class UsageActivityReader {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * When the first LibreSign file was created. A signing request always
	 * belongs to a file, so no LibreSign activity can be older than this.
	 */
	public function firstActivityAt(): ?\DateTimeImmutable {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->min('created_at'))
			->from('libresign_file');
		return $this->fetchDateTime($qb);
	}

	/** When the first certificate of any type was recorded. */
	public function firstCertificateRecordedAt(): ?\DateTimeImmutable {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->min('issued_at'))
			->from('libresign_crl');
		return $this->fetchDateTime($qb);
	}

	public function countStandaloneFilesCreated(ReportPeriod $period): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from('libresign_file', 'f')
			->where($qb->expr()->isNull('f.parent_file_id'))
			->andWhere($qb->expr()->neq('f.node_type', $qb->createNamedParameter(NodeType::ENVELOPE->value)))
			->andWhere($this->notExists($qb, $this->childFiles()))
			->andWhere($this->notExists($qb, $this->identificationDocuments('f.id')));
		$this->whereInPeriod($qb, 'f.created_at', $period);
		return $this->fetchCount($qb);
	}

	public function countEnvelopesCreated(ReportPeriod $period): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from('libresign_file', 'f')
			->where($qb->expr()->isNull('f.parent_file_id'))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('f.node_type', $qb->createNamedParameter(NodeType::ENVELOPE->value)),
				$qb->createFunction('EXISTS (' . $this->childFiles()->getSQL() . ')'),
			));
		$this->whereInPeriod($qb, 'f.created_at', $period);
		return $this->fetchCount($qb);
	}

	public function countSigningRequestsCreated(ReportPeriod $period): int {
		return $this->countSignRequests($period, 'sr.created_at', ParticipantRole::SIGNER);
	}

	public function countSigningRequestsCompleted(ReportPeriod $period): int {
		return $this->countSignRequests($period, 'sr.signed', ParticipantRole::SIGNER);
	}

	public function countSigningRequestsRejected(ReportPeriod $period): int {
		return $this->countSignRequests($period, 'sr.rejected_at', ParticipantRole::SIGNER);
	}

	public function countObserversAdded(ReportPeriod $period): int {
		return $this->countSignRequests($period, 'sr.created_at', ParticipantRole::OBSERVER);
	}

	public function countSigningCertificatesIssued(ReportPeriod $period): int {
		return $this->countSigningCertificates($period, 'issued_at');
	}

	public function countSigningCertificatesRevoked(ReportPeriod $period): int {
		return $this->countSigningCertificates($period, 'revoked_at');
	}

	private function countSignRequests(ReportPeriod $period, string $eventColumn, ParticipantRole $role): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from('libresign_sign_request', 'sr')
			->innerJoin('sr', 'libresign_file', 'f', $qb->expr()->eq('f.id', 'sr.file_id'))
			->where($qb->expr()->isNull('f.parent_file_id'))
			->andWhere($qb->expr()->eq('sr.participant_role', $qb->createNamedParameter($role->value)))
			->andWhere($this->notExists($qb, $this->identificationDocuments('sr.file_id')));
		$this->whereInPeriod($qb, $eventColumn, $period);
		return $this->fetchCount($qb);
	}

	private function countSigningCertificates(ReportPeriod $period, string $eventColumn): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from('libresign_crl')
			->where($qb->expr()->eq('certificate_type', $qb->createNamedParameter(CertificateType::LEAF->value)));
		$this->whereInPeriod($qb, $eventColumn, $period);
		return $this->fetchCount($qb);
	}

	private function childFiles(): IQueryBuilder {
		$sub = $this->db->getQueryBuilder();
		return $sub->select($sub->expr()->literal(1))
			->from('libresign_file', 'child')
			->where($sub->expr()->eq('child.parent_file_id', 'f.id'));
	}

	private function identificationDocuments(string $fileIdColumn): IQueryBuilder {
		$sub = $this->db->getQueryBuilder();
		return $sub->select($sub->expr()->literal(1))
			->from('libresign_id_docs', 'idd')
			->where($sub->expr()->eq('idd.file_id', $fileIdColumn));
	}

	private function notExists(IQueryBuilder $outer, IQueryBuilder $subQuery): string {
		return (string)$outer->createFunction('NOT EXISTS (' . $subQuery->getSQL() . ')');
	}

	private function whereInPeriod(IQueryBuilder $qb, string $column, ReportPeriod $period): void {
		$qb->andWhere($qb->expr()->gte($column, $qb->createNamedParameter($period->start(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
			->andWhere($qb->expr()->lt($column, $qb->createNamedParameter($period->end(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE)));
	}

	private function fetchCount(IQueryBuilder $qb): int {
		$result = $qb->executeQuery();
		$count = $result->fetchOne();
		$result->closeCursor();
		return (int)$count;
	}

	private function fetchDateTime(IQueryBuilder $qb): ?\DateTimeImmutable {
		$result = $qb->executeQuery();
		$value = $result->fetchOne();
		$result->closeCursor();
		if (!is_string($value) || $value === '') {
			return null;
		}
		return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
	}
}
