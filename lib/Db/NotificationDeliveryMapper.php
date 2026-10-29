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
	public const MAX_ATTEMPTS = 5;
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'libresign_notification_delivery', NotificationDelivery::class);
	}

	/**
	 * Registers one delivery for an event, channel and recipient.
	 * A duplicate is never an instruction to resubmit an uncertain delivery.
	 *
	 * @return NotificationDelivery The new or existing record.
	 *                              A previously committed PENDING event may be safely claimed by a later
	 *                              dispatch when a process died before transport submission.
	 */
	public function register(
		string $eventKey,
		string $correlationId,
		string $eventType,
		string $channel,
		int $fileId,
		?int $signRequestId,
		string $ownerId,
		string $encryptedRecipient,
		?string $encryptedMessage = null,
	): NotificationDelivery {
		if (strlen($eventKey) !== 64 || !ctype_xdigit($eventKey)
			|| strlen($correlationId) !== 64 || !ctype_xdigit($correlationId)) {
			throw new \InvalidArgumentException('A 64-character event hash is required');
		}
		$delivery = new NotificationDelivery();
		$delivery->setEventKey($eventKey);
		$delivery->setCorrelationId($correlationId);
		$delivery->setEventType($eventType);
		$delivery->setChannel($channel);
		$delivery->setFileId($fileId);
		$delivery->setSignRequestId($signRequestId);
		$delivery->setOwnerId($ownerId);
		$delivery->setRecipientEnc($encryptedRecipient);
		$delivery->setMessageEnc($encryptedMessage);
		$delivery->setState(NotificationDeliveryState::PENDING->value);
		$delivery->setAttemptCount(0);
		$now = new \DateTime('now', new \DateTimeZone('UTC'));
		$delivery->setCreatedAt($now);
		$delivery->setUpdatedAt($now);

		// PostgreSQL aborts the entire surrounding transaction on a duplicate
		// key error. A savepoint keeps the sign request transaction usable while
		// still allowing the unique index to arbitrate concurrent registrations.
		$savepoint = $this->db->inTransaction() ? 'ls_notify_' . bin2hex(random_bytes(8)) : null;
		if ($savepoint !== null) {
			$this->db->executeStatement('SAVEPOINT ' . $savepoint);
		}
		try {
			try {
				return $this->insert($delivery);
			} catch (DatabaseException $e) {
				if ($savepoint !== null) {
					$this->db->executeStatement('ROLLBACK TO SAVEPOINT ' . $savepoint);
				}
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
		} finally {
			if ($savepoint !== null) {
				$this->db->executeStatement('RELEASE SAVEPOINT ' . $savepoint);
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

	/** @return list<NotificationDelivery> */
	public function findForEvent(string $correlationId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('correlation_id', $qb->createNamedParameter($correlationId)));
		return $this->findEntities($qb);
	}

	/** Claim is a compare-and-swap, so a second dispatcher cannot send again. */
	public function claim(int $deliveryId): bool {
		$qb = $this->db->getQueryBuilder();
		$now = new \DateTime('now', new \DateTimeZone('UTC'));
		return $qb->update($this->getTableName())
			->set('state', $qb->createNamedParameter(NotificationDeliveryState::SENDING->value))
			->set('attempt_count', 'attempt_count + 1')
			->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lt('attempt_count', $qb->createNamedParameter(self::MAX_ATTEMPTS, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('state', $qb->createNamedParameter(NotificationDeliveryState::PENDING->value)),
				$qb->expr()->andX(
					$qb->expr()->eq('state', $qb->createNamedParameter(NotificationDeliveryState::TEMPORARY_FAILURE->value)),
					$qb->expr()->lte('next_attempt_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE)),
				),
			))
			->executeStatement() === 1;
	}

	public function finish(int $deliveryId, NotificationDeliveryState $state, ?\DateTime $retryAt = null): void {
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
		$update = $qb->update($this->getTableName());
		if ($retryAt === null) {
			$update->set('message_enc', $qb->createNamedParameter(null));
		}
		$updated = $update
			->set('state', $qb->createNamedParameter($state->value))
			->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->set('next_attempt_at', $qb->createNamedParameter($retryAt, $retryAt === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('state', $qb->createNamedParameter(NotificationDeliveryState::SENDING->value)))
			->executeStatement();
		if ($updated !== 1) {
			throw new \LogicException('Notification delivery state changed during dispatch');
		}
	}

	/**
	 * Invalidate obsolete invitations while the signing-state mutation is
	 * protected by its database transaction. A claimed submission is never
	 * rewritten because the provider may already have accepted it.
	 */
	public function cancelPendingInvitations(int $signRequestId): int {
		$qb = $this->db->getQueryBuilder();
		return $qb->update($this->getTableName())
			->set('state', $qb->createNamedParameter(NotificationDeliveryState::CANCELED->value))
			->set('message_enc', $qb->createNamedParameter(null))
			->set('updated_at', $qb->createNamedParameter(new \DateTime('now', new \DateTimeZone('UTC')), IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('sign_request_id', $qb->createNamedParameter($signRequestId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('event_type', $qb->createNamedParameter('sign_request')))
			->andWhere($qb->expr()->in('state', $qb->createNamedParameter([NotificationDeliveryState::PENDING->value, NotificationDeliveryState::TEMPORARY_FAILURE->value], IQueryBuilder::PARAM_STR_ARRAY)))
			->executeStatement();
	}

	public function findById(int $deliveryId): NotificationDelivery {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)));
		/** @var NotificationDelivery */
		return $this->findEntity($qb);
	}

	/** @return list<NotificationDelivery> */
	public function findPending(int $limit = 50): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('state', $qb->createNamedParameter(NotificationDeliveryState::PENDING->value)))
			->orderBy('created_at', 'ASC')->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/** @return list<NotificationDelivery> Work due now; future retries are never queued early. */
	public function findDue(int $limit = 50): array {
		$qb = $this->db->getQueryBuilder();
		$now = new \DateTime('now', new \DateTimeZone('UTC'));
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->orX(
				$qb->expr()->eq('state', $qb->createNamedParameter(NotificationDeliveryState::PENDING->value)),
				$qb->expr()->andX(
					$qb->expr()->eq('state', $qb->createNamedParameter(NotificationDeliveryState::TEMPORARY_FAILURE->value)),
					$qb->expr()->lte('next_attempt_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE)),
				),
			))
			->orderBy('created_at', 'ASC')->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/** A corrupt handoff cannot safely enter a transport attempt. */
	public function failPending(int $deliveryId): bool {
		$qb = $this->db->getQueryBuilder();
		return $qb->update($this->getTableName())
			->set('state', $qb->createNamedParameter(NotificationDeliveryState::FAILED->value))
			->set('message_enc', $qb->createNamedParameter(null))
			->set('updated_at', $qb->createNamedParameter(new \DateTime('now', new \DateTimeZone('UTC')), IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('state', $qb->createNamedParameter([NotificationDeliveryState::PENDING->value, NotificationDeliveryState::TEMPORARY_FAILURE->value], IQueryBuilder::PARAM_STR_ARRAY)))
			->executeStatement() === 1;
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
	 * An interrupted submission has no reliable acceptance result.
	 * Never move a stale sending record back to pending.
	 */
	public function markStaleUnknown(int $deliveryId, \DateTime $before): bool {
		$qb = $this->db->getQueryBuilder();
		return $qb->update($this->getTableName())
			->set('state', $qb->createNamedParameter(NotificationDeliveryState::UNKNOWN->value))
			->set('message_enc', $qb->createNamedParameter(null))
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
			->set('message_enc', $update->createNamedParameter(null))
			->where($update->expr()->in('id', $update->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->executeStatement();
	}
}
