<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025-2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\IdentifyMethodService;
use OCA\Libresign\Service\Notification\GatewayChannel;
use OCA\Libresign\Service\Notification\GatewayNotificationComposer;
use OCA\Libresign\Service\Notification\GatewayPayload;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\Notification\NotificationModule;
use OCA\Libresign\Service\NotificationDeliveryService;
use OCA\Libresign\Service\TwofactorGatewayService;
use OCP\EventDispatcher\Event;

/** Plan gateway deliveries, not transport execution or message rendering. */
class TwofactorGatewayListener implements NotificationModule {
	public function __construct(
		private TwofactorGatewayService $twofactorGatewayService,
		private NotificationDeliveryService $deliveryService,
		private GatewayChannel $channel,
		private GatewayNotificationComposer $composer,
	) {
	}

	/** @return list<class-string<Event>> */
	#[\Override]
	public static function supportedEvents(): array {
		return [SendSignNotificationEvent::class, SignedEvent::class];
	}

	#[\Override]
	public function handle(Event $event): void {
		match ($event::class) {
			SendSignNotificationEvent::class => $this->sendNotification(
				$event->getSignRequest(), $event->getIdentifyMethod(), $event->getLibreSignFile(),
				'sign_request', $event->getDeliveryEventId(),
			),
			SignedEvent::class => $this->sendNotification(
				$event->getSignRequest(), $event->getIdentifyMethod(), $event->getLibreSignFile(),
				'file_signed', $event->getDeliveryEventId(),
			),
		};
	}

	private function sendNotification(
		SignRequest $signRequest,
		IIdentifyMethod $identifyMethod,
		FileEntity $file,
		string $eventType,
		string $eventId,
	): void {
		$entity = $identifyMethod->getEntity();
		if ($entity->isDeletedAccount()
			|| !in_array($entity->getIdentifierKey(), IdentifyMethodService::IDENTIFY_TWOFACTOR_GATEWAY_METHODS, true)) {
			return;
		}
		if (!$this->twofactorGatewayService->isEnabled()) {
			return;
		}
		$recipient = $entity->getIdentifierValue();
		if (trim($recipient) === '') {
			return;
		}
		$gatewayName = IdentifyMethodService::resolveTwofactorGatewayName($entity->getIdentifierKey());
		$body = $this->composer->compose($eventType, $signRequest, $file, $entity->getIdentifierKey());
		$this->deliveryService->dispatch(new NotificationMessage(
			$eventId, $eventType, 'gateway:' . $gatewayName, $file, $signRequest,
			$recipient, new GatewayPayload($gatewayName, $body),
			$eventType === 'sign_request' ? $entity->getIdentifierKey() : null,
		), $this->channel);
	}
}
