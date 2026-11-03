<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCA\Libresign\Enum\NotificationDeliveryState;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<NotificationAttempt> */
class NotificationAttemptMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_notification_attempt', NotificationAttempt::class);
	}

	public function begin(int $deliveryId): NotificationAttempt {
		$attempt = new NotificationAttempt();
		$attempt->setDeliveryId($deliveryId);
		$attempt->setState(NotificationDeliveryState::SENDING->value);
		$attempt->setStartedAt(new \DateTime('now', new \DateTimeZone('UTC')));
		return $this->insert($attempt);
	}

	public function finish(int $attemptId, NotificationDeliveryState $state, ?string $reasonCode): void {
		$qb = $this->db->getQueryBuilder();
		$updated = $qb->update($this->getTableName())
			->set('state', $qb->createNamedParameter($state->value))
			->set('reason_code', $qb->createNamedParameter($reasonCode))
			->set('finished_at', $qb->createNamedParameter(new \DateTime('now', new \DateTimeZone('UTC')), IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($attemptId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('state', $qb->createNamedParameter(NotificationDeliveryState::SENDING->value)))
			->executeStatement();
		if ($updated !== 1) {
			throw new \LogicException('Notification attempt state changed during dispatch');
		}
	}

	/** @return list<NotificationAttempt> */
	public function findForDelivery(int $deliveryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('delivery_id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)))
			->orderBy('started_at', 'ASC');
		return $this->findEntities($qb);
	}

	/** Close interrupted submissions without marking them as safe to retry. */
	public function finishInterrupted(int $deliveryId): int {
		$qb = $this->db->getQueryBuilder();
		return $qb->update($this->getTableName())
			->set('state', $qb->createNamedParameter(NotificationDeliveryState::UNKNOWN->value))
			->set('reason_code', $qb->createNamedParameter('submission_interrupted'))
			->set('finished_at', $qb->createNamedParameter(new \DateTime('now', new \DateTimeZone('UTC')), IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('delivery_id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('state', $qb->createNamedParameter(NotificationDeliveryState::SENDING->value)))
			->executeStatement();
	}

	public function deleteOlderThan(\DateTime $before, int $limit = 500): int {
		$qb = $this->db->getQueryBuilder();
		$rows = $qb->select('id')->from($this->getTableName())
			->where($qb->expr()->lt('finished_at', $qb->createNamedParameter($before, IQueryBuilder::PARAM_DATETIME_MUTABLE)))
			->setMaxResults($limit)
			->executeQuery()->fetchAllAssociative();
		if ($rows === []) {
			return 0;
		}
		$ids = array_map(static fn (array $row): int => (int)$row['id'], $rows);
		$delete = $this->db->getQueryBuilder();
		return $delete->delete($this->getTableName())
			->where($delete->expr()->in('id', $delete->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->executeStatement();
	}
}
