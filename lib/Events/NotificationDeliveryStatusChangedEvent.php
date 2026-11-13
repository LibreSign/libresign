<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Events;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IWebhookCompatibleEvent;

/** Result of one persisted channel-submission attempt, NOT recipient delivery. */
final class NotificationDeliveryStatusChangedEvent extends Event implements IWebhookCompatibleEvent {
	public const CONTRACT_VERSION = 1;

	private function __construct(
		private readonly string $requestUuid,
		private readonly string $requestType,
		private readonly int $deliveryId,
		private readonly string $channel,
		private readonly string $state,
		private readonly int $attempt,
		private readonly string $eventId,
	) {
		parent::__construct();
	}

	public static function fromPersistedDelivery(FileEntity $root, NotificationDelivery $delivery): ?self {
		$state = NotificationDeliveryState::tryFrom($delivery->getState());
		if ($root->getUuid() === '' || $delivery->getId() === null || $delivery->getAttemptCount() < 1
			|| $state === null
			|| in_array($state, [NotificationDeliveryState::PENDING, NotificationDeliveryState::SENDING], true)) {
			return null;
		}

		return new self(
			$root->getUuid(),
			$root->isEnvelope() ? 'envelope' : 'document',
			$delivery->getId(),
			$delivery->getChannel(),
			$state->value,
			$delivery->getAttemptCount(),
			'libresign.notification.delivery.v1:' . $delivery->getId() . ':' . $delivery->getAttemptCount() . ':' . $state->value,
		);
	}

	#[\Override]
	public function getWebhookSerializable(): array {
		return [
			'contractVersion' => self::CONTRACT_VERSION,
			'eventId' => $this->eventId,
			'requestUuid' => $this->requestUuid,
			'requestType' => $this->requestType,
			'deliveryId' => $this->deliveryId,
			'channel' => $this->channel,
			'state' => $this->state,
			'attempt' => $this->attempt,
		];
	}
}
