<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\AppInfo\Application as AppInfoApplication;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Events\SignRequestCanceledEvent;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\Notification\InAppChannel;
use OCA\Libresign\Service\Notification\InAppPayload;
use OCA\Libresign\Service\Notification\NotificationHistoryService;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\Notification\NotificationModule;
use OCA\Libresign\Service\NotificationDeliveryService;
use OCA\Libresign\Service\NotificationPreferenceResolver;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Notification\IManager;

/**
 * @implements NotificationModule
 */
class NotificationListener implements NotificationModule {
	public function __construct(
		private IManager $notificationManager,
		protected IUserSession $userSession,
		private ITimeFactory $timeFactory,
		protected IURLGenerator $url,
		private NotificationPreferenceResolver $notificationPreferenceResolver,
		private NotificationDeliveryService $deliveryService,
		private InAppChannel $channel,
		private NotificationHistoryService $history,
	) {
	}

	/** @return list<class-string<Event>> */
	public static function supportedEvents(): array {
		return [SendSignNotificationEvent::class, SignedEvent::class, SignRequestCanceledEvent::class];
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof SendSignNotificationEvent) {
			$this->sendSignNotification(
				$event->getSignRequest(),
				$event->getLibreSignFile(),
				$event->getIdentifyMethod(),
				$event->getDeliveryEventId(),
			);
		} elseif ($event instanceof SignedEvent) {
			$this->sendSignedNotification(
				$event->getSignRequest(),
				$event->getLibreSignFile(),
				$event->getIdentifyMethod(),
				$event->getDeliveryEventId(),
			);
		} elseif ($event instanceof SignRequestCanceledEvent) {
			$this->sendCanceledNotification(
				$event->getSignRequest(),
				$event->getLibreSignFile(),
				$event->getIdentifyMethod(),
				$event->getDeliveryEventId(),
			);
		}
	}

	private function sendSignNotification(
		SignRequest $signRequest,
		FileEntity $libreSignFile,
		IIdentifyMethod $identifyMethod,
		string $eventId,
	): void {
		$actor = $this->userSession->getUser();
		if (!$actor instanceof IUser) {
			return;
		}
		if ($identifyMethod->getEntity()->isDeletedAccount()) {
			return;
		}
		if ($identifyMethod->getName() !== 'account') {
			return;
		}
		$notificationDisabled = $this->notificationPreferenceResolver->isInAppNotificationDisabled(
			$identifyMethod->getEntity()->getIdentifierValue(),
			SendSignNotificationEvent::FILE_TO_SIGN,
			true,
		);
		if ($notificationDisabled) {
			return;
		}
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp(AppInfoApplication::APP_ID)
			->setObject('signRequest', (string)$signRequest->getId())
			->setDateTime((new \DateTime())->setTimestamp($this->timeFactory->now()->getTimestamp()))
			->setUser($identifyMethod->getEntity()->getIdentifierValue());
		$subjectParameters = [
			'from' => $this->getUserParameter(
				$actor->getUID(),
				$actor->getDisplayName(),
			),
			'file' => $this->getFileParameter($signRequest, $libreSignFile),
			'signRequest' => [
				'type' => 'sign-request',
				'id' => (string)$signRequest->getId(),
				'name' => $actor->getDisplayName(),
			],
		];
		$subject = $this->history->isFirst($signRequest, 'notify') ? 'new_sign_request' : 'update_sign_request';
		$notification->setSubject($subject, $subjectParameters);
		$this->deliveryService->dispatch(new NotificationMessage(
			$eventId, 'sign_request', 'nextcloud', $libreSignFile, $signRequest,
			$identifyMethod->getEntity()->getIdentifierValue(), new InAppPayload($notification, $subjectParameters),
			'notify',
		), $this->channel);
	}

	private function sendSignedNotification(
		SignRequest $signRequest,
		FileEntity $libreSignFile,
		IIdentifyMethod $identifyMethod,
		string $eventId,
	): void {

		$actorId = $libreSignFile->getUserId();

		if ($identifyMethod->getEntity()->isDeletedAccount()) {
			return;
		}
		$notificationDisabled = $this->notificationPreferenceResolver->isInAppNotificationDisabled(
			$libreSignFile->getUserId(),
			SignedEvent::FILE_SIGNED,
			true,
		);
		if ($notificationDisabled) {
			return;
		}

		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp(AppInfoApplication::APP_ID)
			->setObject('signedFile', (string)$signRequest->getId())
			->setDateTime((new \DateTime())->setTimestamp($this->timeFactory->now()->getTimestamp())->setTimezone(new \DateTimeZone('UTC')))
			->setUser($actorId)
			->setSubject('file_signed', [
				'from' => $this->getFromSignedParameter(
					$identifyMethod->getEntity()->getIdentifierKey(),
					$identifyMethod->getEntity()->getIdentifierValue(),
					$signRequest->getDisplayName(),
					$identifyMethod->getEntity()->getId(),
				),
				'file' => [
					'type' => 'file',
					'id' => (string)$libreSignFile->getNodeId(),
					'name' => $libreSignFile->getName(),
					'path' => $libreSignFile->getName(),
					'link' => $this->url->linkToRouteAbsolute('libresign.page.indexFPath', [
						'path' => 'validation/' . $libreSignFile->getUuid(),
					]),
				],
				'signedFile' => [
					'type' => 'signer',
					'id' => (string)$signRequest->getId(),
					'name' => $signRequest->getDisplayName(),
				],
			]);

		$this->deliveryService->dispatch(new NotificationMessage(
			$eventId, 'file_signed', 'nextcloud', $libreSignFile, $signRequest,
			$actorId, new InAppPayload($notification),
		), $this->channel);
	}

	private function sendCanceledNotification(
		SignRequest $signRequest,
		FileEntity $libreSignFile,
		IIdentifyMethod $identifyMethod,
		string $eventId,
	): void {
		$actor = $this->userSession->getUser();
		if (!$actor instanceof IUser) {
			return;
		}
		if ($identifyMethod->getEntity()->isDeletedAccount()) {
			return;
		}
		$notificationDisabled = $this->notificationPreferenceResolver->isInAppNotificationDisabled(
			$identifyMethod->getEntity()->getIdentifierValue(),
			SignRequestCanceledEvent::SIGN_REQUEST_CANCELED,
			true,
		);
		if ($notificationDisabled) {
			return;
		}

		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp(AppInfoApplication::APP_ID)
			->setObject('signRequest', (string)$signRequest->getId())
			->setDateTime((new \DateTime())->setTimestamp($this->timeFactory->now()->getTimestamp()))
			->setUser($identifyMethod->getEntity()->getIdentifierValue())
			->setSubject('sign_request_canceled', [
				'from' => $this->getUserParameter(
					$actor->getUID(),
					$actor->getDisplayName(),
				),
				'file' => [
					'type' => 'file',
					'id' => (string)$libreSignFile->getNodeId(),
					'name' => $libreSignFile->getName(),
					'path' => $libreSignFile->getName(),
				],
				'signRequest' => [
					'type' => 'sign-request',
					'id' => (string)$signRequest->getId(),
					'name' => $actor->getDisplayName(),
				],
			]);

		$this->deliveryService->dispatch(new NotificationMessage(
			$eventId, 'sign_request_canceled', 'nextcloud', $libreSignFile, $signRequest,
			$identifyMethod->getEntity()->getIdentifierValue(), new InAppPayload($notification),
		), $this->channel);
	}

	/**
	 * @psalm-return array{type: 'file', id: string, name: string, path: string, link: string}
	 */
	protected function getFileParameter(SignRequest $signRequest, FileEntity $libreSignFile): array {
		if ($signRequest->isObserver()) {
			$link = $this->url->linkToRouteAbsolute(
				'libresign.page.indexFPath',
				['path' => 'filelist/sign'],
			);
			$link .= '?uuid=' . urlencode($libreSignFile->getUuid());
		} else {
			$link = $this->url->linkToRouteAbsolute('libresign.page.signFPath', ['uuid' => $signRequest->getUuid(), 'path' => 'pdf']);
		}

		return [
			'type' => 'file',
			'id' => (string)$libreSignFile->getNodeId(),
			'name' => $libreSignFile->getName(),
			'path' => $libreSignFile->getName(),
			'link' => $link,
		];
	}

	protected function getUserParameter(
		string $userId,
		string $displayName,
	): array {
		return [
			'type' => 'user',
			'id' => $userId,
			'name' => $displayName,
		];
	}

	protected function getFromSignedParameter(
		string $type,
		string $identifier,
		string $displayName,
		int $identifyMethodId,
	): array {

		if ($type === 'account') {
			return $this->getUserParameter(
				$identifier,
				$displayName
			);
		}

		return [
			'type' => 'signer',
			'id' => (string)$identifyMethodId,
			'name' => $displayName,
		];
	}
}
