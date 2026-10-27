<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Service\Notification\NotificationChannel;
use OCA\Libresign\Service\Notification\NotificationDeliveryKeyFactory;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\Notification\NotificationSubmissionService;
use OCP\IDBConnection;
use OCP\Security\ICrypto;

/** Synchronous delivery facade. Transport and database state transitions are independent. */
class NotificationDeliveryService {
	public function __construct(
		private NotificationDeliveryMapper $deliveries,
		private NotificationDeliveryKeyFactory $keys,
		private NotificationSubmissionService $submission,
		private ICrypto $crypto,
		private IDBConnection $db,
	) {
	}

	/** @return bool Whether this invocation claimed and attempted delivery. */
	public function dispatch(NotificationMessage $message, NotificationChannel $channel): bool {
		if ($this->db->inTransaction()) {
			throw new \LogicException('Notification dispatch during an open domain transaction is forbidden');
		}
		$keys = $this->keys->keys($message, $channel);
		$delivery = $this->deliveries->register(
			$keys['eventKey'],
			$keys['correlationId'],
			$message->eventType,
			$message->channel,
			$message->file->getId(),
			$message->signRequest->getId(),
			$message->file->getUserId(),
			$this->crypto->encrypt($message->recipient),
		);
		return $this->submission->submit($delivery, $message, $channel);
	}
}
