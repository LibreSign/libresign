<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\BackgroundJob\DeliverNotification;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Service\Notification\NotificationChannel;
use OCA\Libresign\Service\Notification\NotificationDeliveryModeResolver;
use OCA\Libresign\Service\Notification\NotificationDeliveryRegistrar;
use OCA\Libresign\Service\Notification\NotificationEnvelopeCodec;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\Notification\NotificationSubmissionService;
use OCA\Libresign\Service\Worker\WorkerActivationService;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;

/**
 * Register an intent first. The domain transaction owns the outbox row and
 * queued job atomically. Never contact a provider before it commits.
 */
class NotificationDeliveryService {
	/** @var array<int, array{NotificationDelivery, NotificationMessage, NotificationChannel}> */
	private array $afterCommit = [];

	/** @var array<int, string> Delivery ID => event key, checked after commit. */
	private array $notificationWakes = [];

	public function __construct(
		private NotificationDeliveryRegistrar $registrar,
		private NotificationSubmissionService $submission,
		private NotificationEnvelopeCodec $codec,
		private NotificationDeliveryModeResolver $mode,
		private IJobList $jobs,
		private IDBConnection $db,
		private NotificationDeliveryMapper $deliveries,
		private WorkerActivationService $workerActivationService,
	) {
	}

	/** @return bool Whether this invocation registered or submitted a delivery. */
	public function dispatch(NotificationMessage $message, NotificationChannel $channel): bool {
		try {
			$delivery = $this->registrar->register($message, $channel, $this->codec->encode($message));
			$async = $this->mode->isAsync();
			$inTransaction = $this->db->inTransaction();
			if ($inTransaction || $async) {
				// Job insertion uses the same database connection and rolls back with
				// a failed signer mutation. The timed recovery job is a second path.
				$this->jobs->add(DeliverNotification::class, ['id' => $delivery->getId()]);
				if (!$async) {
					$this->afterCommit[$delivery->getId()] = [$delivery, $message, $channel];
				} elseif ($inTransaction) {
					// Never start a worker against an uncommitted outbox row.
					$this->notificationWakes[$delivery->getId()] = $delivery->getEventKey();
				} else {
					$this->workerActivationService->ensureNotificationWorkerRunning();
				}
				return true;
			}
		} catch (\Exception $error) {
			if ($this->db->inTransaction() && !$error instanceof \LogicException) {
				throw new \LogicException('Failed to persist notification intent with the signing request', 0, $error);
			}
			throw $error;
		}
		// The compatibility path remains synchronous outside domain transactions.
		// A transport failure cannot undo the already committed business event.
		return $this->submission->submit($delivery, $message, $channel);
	}
	/**
	 * Preserve synchronous delivery after, never inside, domain transactions.
	 * A request that committed must succeed even if SMTP fails afterwards.
	 */
	public function flushCommitted(): void {
		if ($this->db->inTransaction()) {
			return;
		}
		// Only wake for an event that survived the business transaction.
		// A rollback may leave a stale in-memory ID, or an ID may be reused.
		$wakes = $this->notificationWakes;
		$this->notificationWakes = [];
		foreach ($wakes as $id => $eventKey) {
			try {
				if ($this->deliveries->findById($id)->getEventKey() === $eventKey) {
					$this->workerActivationService->ensureNotificationWorkerRunning();
					break;
				}
			} catch (\Exception) {
				// A missing or rolled-back intent must not trigger early execution.
			}
		}

		$buffer = $this->afterCommit;
		$this->afterCommit = [];
		foreach ($buffer as [$delivery, $message, $channel]) {
			try {
				// A rolled-back transaction can reuse an auto-increment ID. Never
				// submit its former message for a new event with the same ID.
				$current = $this->deliveries->findById($delivery->getId());
				if ($current->getEventKey() !== $delivery->getEventKey()) {
					continue;
				}
				$this->submission->submit($delivery, $message, $channel);
			} catch (\Exception) {
				// Submission tracks and logs the outcome. The domain already committed.
			}
		}
	}

}
