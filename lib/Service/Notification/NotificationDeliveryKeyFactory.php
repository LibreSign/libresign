<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCP\IConfig;

/** Derive correlation and idempotency keys without recording recipient data in plaintext. */
final class NotificationDeliveryKeyFactory {
	public function __construct(
		private IConfig $config,
	) {
	}

	/** One opaque ID shared by all channels for this business event. */
	public function correlationId(string $sourceEventId, string $eventType, int $fileId, ?int $signRequestId): string {
		if ($sourceEventId === '') {
			throw new \InvalidArgumentException('Notification event identity is required');
		}
		$secret = $this->config->getSystemValueString('secret');
		if ($secret === '') {
			throw new \LogicException('The Nextcloud instance secret is not available');
		}
		$event = implode("\0", [$sourceEventId, $eventType, (string)$fileId, (string)$signRequestId]);
		return hash_hmac('sha256', $event, hash_hmac('sha256', 'libresign-notification-event-v1', $secret, true));
	}

	/** @return array{correlationId: string, eventKey: string} */
	public function keys(NotificationMessage $message, NotificationChannel $transport): array {
		if ($message->sourceEventId === '' || trim($message->recipient) === '' || !$transport->supports($message->channel)) {
			throw new \InvalidArgumentException('Notification event, channel or recipient is invalid');
		}
		$secret = $this->config->getSystemValueString('secret');
		if ($secret === '') {
			throw new \LogicException('The Nextcloud instance secret is not available');
		}
		$recipient = trim($message->recipient);
		if ($message->channel === 'mail') {
			$recipient = strtolower($recipient);
		}
		$keyed = static fn (string $context, string $payload): string => hash_hmac(
			'sha256', $payload,
			hash_hmac('sha256', $context, $secret, true),
		);
		return [
			'correlationId' => $this->correlationId(
				$message->sourceEventId, $message->eventType, $message->file->getId(), $message->signRequest->getId(),
			),
			'eventKey' => $keyed('libresign-notification-v2', implode("\0", [
				$message->sourceEventId,
				$message->eventType,
				$message->channel,
				(string)$message->file->getId(),
				(string)$message->signRequest->getId(),
				$recipient,
			])),
		];
	}
}
