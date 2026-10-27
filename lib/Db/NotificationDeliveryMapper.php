<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Db;

use OCA\Libresign\Enum\NotificationDeliveryState;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception as DatabaseException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<NotificationDelivery> */
class NotificationDeliveryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_notification_delivery', NotificationDelivery::class);
	}

	/**
	 * Inserts one logical notification, without ever overwriting an earlier
	 * outcome. A duplicate event is never an instruction to retry SMTP.
	 *
	 * @return NotificationDelivery The new or existing record.
	 *                              A previously committed PENDING event may be safely claimed by a later
	 *                              dispatch when a process died before transport submission.
	 */
	public function register(
		string $eventKey,
		string $eventType,
		int $fileId,
		?int $signRequestId,
		string $ownerId,
		string $encryptedRecipient,
	): NotificationDelivery {
		if (strlen($eventKey) !== 64 || !ctype_xdigit($eventKey)) {
			throw new \InvalidArgumentException('A 64-character event hash is required');
		}
		$delivery = new NotificationDelivery();
		$delivery->setEventKey($eventKey);
		$delivery->setEventType($eventType);
		$delivery->setFileId($fileId);
		$delivery->setSignRequestId($signRequestId);
		$delivery->setOwnerId($ownerId);
		$delivery->setRecipientEnc($encryptedRecipient);
		$delivery->setState(NotificationDeliveryState::PENDING->value);
		$now = new \DateTime('now', new \DateTimeZone('UTC'));
		$delivery->setCreatedAt($now);
		$delivery->setUpdatedAt($now);

		try {
			return $this->insert($delivery);
		} catch (DatabaseException $e) {
			if ($e->getReason() !== DatabaseException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			// A second writer raced us. Verify the event key; the exception
			// could also be an unrelated constraint violation.
			try {
				return $this->findByEventKey($eventKey);
			} catch (DoesNotExistException) {
				throw $e;
			}
		}
	}

	public function findByEventKey(string $eventKey): NotificationDelivery {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('event_key', $qb->createNamedParameter($eventKey)));
		/** @var NotificationDelivery */
		return $this->findEntity($qb);
	}

	/** Claim is a compare-and-swap, so a second dispatcher cannot send again. */
	public function claim(int $deliveryId): bool {
		$qb = $this->db->getQueryBuilder();
		$now = new \DateTime('now', new \DateTimeZone('UTC'));
		return $qb->update($this->getTableName())
			->set('state', $qb->createNamedParameter(NotificationDeliveryState::SENDING->value))
			->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('state', $qb->createNamedParameter(NotificationDeliveryState::PENDING->value)))
			->executeStatement() === 1;
	}

	public function finish(int $deliveryId, NotificationDeliveryState $state): void {
		if (!in_array($state, [
			NotificationDeliveryState::ACCEPTED,
			NotificationDeliveryState::TEMPORARY_FAILURE,
			NotificationDeliveryState::PERMANENT_FAILURE,
			NotificationDeliveryState::FAILED,
			NotificationDeliveryState::UNKNOWN,
		], true)) {
			throw new \InvalidArgumentException('Invalid terminal notification state');
		}
		$qb = $this->db->getQueryBuilder();
		$now = new \DateTime('now', new \DateTimeZone('UTC'));
		$updated = $qb->update($this->getTableName())
			->set('state', $qb->createNamedParameter($state->value))
			->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('state', $qb->createNamedParameter(NotificationDeliveryState::SENDING->value)))
			->executeStatement();
		if ($updated !== 1) {
			throw new \LogicException('Notification delivery state changed during dispatch');
		}
	}

	/** @return list<NotificationDelivery> */
	public function findStaleSending(\DateTime $before, int $limit = 200): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('state', $qb->createNamedParameter(NotificationDeliveryState::SENDING->value)))
			->andWhere($qb->expr()->lt('updated_at', $qb->createNamedParameter($before, IQueryBuilder::PARAM_DATETIME_MUTABLE)))
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/**
	 * A crash after SMTP submission has no reliable delivery result.
	 * Never move a stale sending record back to pending.
	 */
	public function markStaleUnknown(int $deliveryId, \DateTime $before): bool {
		$qb = $this->db->getQueryBuilder();
		return $qb->update($this->getTableName())
			->set('state', $qb->createNamedParameter(NotificationDeliveryState::UNKNOWN->value))
			->set('updated_at', $qb->createNamedParameter(new \DateTime('now', new \DateTimeZone('UTC')), IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('state', $qb->createNamedParameter(NotificationDeliveryState::SENDING->value)))
			->andWhere($qb->expr()->lt('updated_at', $qb->createNamedParameter($before, IQueryBuilder::PARAM_DATETIME_MUTABLE)))
			->executeStatement() === 1;
	}

	/**
	 * Redact recipient and owner data after an explicitly configured retention
	 * period, but KEEP the event key and terminal state as a deduplication
	 * tombstone. Deleting the delivery would allow a replay to submit again.
	 */
	public function redactOlderThan(\DateTime $before, int $limit = 500): int {
		$qb = $this->db->getQueryBuilder();
		$rows = $qb->select('id')->from($this->getTableName())
			->where($qb->expr()->lt('updated_at', $qb->createNamedParameter($before, IQueryBuilder::PARAM_DATETIME_MUTABLE)))
			->andWhere($qb->expr()->neq('recipient_enc', $qb->createNamedParameter('')))
			->andWhere($qb->expr()->in('state', $qb->createNamedParameter([
				NotificationDeliveryState::ACCEPTED->value,
				NotificationDeliveryState::TEMPORARY_FAILURE->value,
				NotificationDeliveryState::PERMANENT_FAILURE->value,
				NotificationDeliveryState::FAILED->value,
				NotificationDeliveryState::UNKNOWN->value,
				NotificationDeliveryState::CANCELED->value,
			], IQueryBuilder::PARAM_STR_ARRAY)))
			->setMaxResults($limit)
			->executeQuery()->fetchAllAssociative();
		if ($rows === []) {
			return 0;
		}
		$ids = array_map(static fn (array $row): int => (int)$row['id'], $rows);
		$update = $this->db->getQueryBuilder();
		return $update->update($this->getTableName())
			->set('recipient_enc', $update->createNamedParameter(''))
			->set('owner_id', $update->createNamedParameter(''))
			->where($update->expr()->in('id', $update->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->executeStatement();
	}
}
