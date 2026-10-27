<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCP\IDBConnection;
use OCP\Security\ICrypto;

/**
 * Prepare a durable delivery identity before invoking any external transport.
 *
 * Keep database, identity and encryption concerns outside the transport
 * orchestration. Registration is intentionally separate from attempt claiming.
 */
final class NotificationDeliveryRegistrar {
	public function __construct(
		private NotificationDeliveryMapper $deliveries,
		private NotificationDeliveryKeyFactory $keys,
		private ICrypto $crypto,
		private IDBConnection $db,
	) {
	}

	public function register(NotificationMessage $message, NotificationChannel $channel, ?string $encodedMessage = null): NotificationDelivery {
		$keys = $this->keys->keys($message, $channel);
		try {
			return $this->deliveries->register(
				$keys['eventKey'],
				$keys['correlationId'],
				$message->eventType,
				$message->channel,
				$message->file->getId(),
				$message->signRequest->getId(),
				$message->file->getUserId(),
				$this->crypto->encrypt($message->recipient),
				$encodedMessage,
			);
		} catch (\OCP\DB\Exception $e) {
			// The domain transaction must roll back when an intent cannot be stored.
			throw new \LogicException('Notification intent could not be persisted', 0, $e);
		}
	}
}
