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
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Notification\IManager;

/**
 * @template-implements IEventListener<Event>
 */
class NotificationListener implements IEventListener {
	public function __construct(
		private IManager $notificationManager,
		protected IUserSession $userSession,
		private ITimeFactory $timeFactory,
		protected IURLGenerator $url,
		private SignRequestMapper $signRequestMapper,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof SendSignNotificationEvent) {
			$this->sendSignNotification(
				$event->getSignRequest(),
				$event->getLibreSignFile(),
				$event->getIdentifyMethod(),
			);
		} elseif ($event instanceof SignedEvent) {
			$this->sendSignedNotification(
				$event->getSignRequest(),
				$event->getLibreSignFile(),
				$event->getIdentifyMethod(),
			);
		}
	}

	private function sendSignNotification(
		SignRequest $signRequest,
		FileEntity $libreSignFile,
		IIdentifyMethod $identifyMethod,
	): void {
		$actor = $this->userSession->getUser();
		if (!$actor instanceof IUser) {
			return;
		}
		if ($identifyMethod->getEntity()->isDeletedAccount()) {
			return;
		}
		$notificationDisabled = $this->isNotificationDisabledAtActivity(
			$identifyMethod->getEntity()->getIdentifierValue(),
			SendSignNotificationEvent::FILE_TO_SIGN,
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
		$isFirstNotification = $this->signRequestMapper->incrementNotificationCounter($signRequest, 'notify');
		if ($isFirstNotification) {
			$subject = 'new_sign_request';
		} else {
			$subject = 'update_sign_request';
		}
		$notification->setSubject($subject, [
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
		]);
		$this->notificationManager->notify($notification);
	}

	private function sendSignedNotification(
		SignRequest $signRequest,
		FileEntity $libreSignFile,
		IIdentifyMethod $identifyMethod,
	): void {

		$actorId = $libreSignFile->getUserId();

		if ($identifyMethod->getEntity()->isDeletedAccount()) {
			return;
		}
		$notificationDisabled = $this->isNotificationDisabledAtActivity(
			$libreSignFile->getUserId(),
			SignedEvent::FILE_SIGNED,
		);
		if ($notificationDisabled) {
			return;
		}

		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp(AppInfoApplication::APP_ID)
			->setObject('signedFile', (string)$signRequest->getId())
			->setDateTime((new \DateTime())->setTimestamp($this->timeFactory->now()->getTimestamp()))
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

		$this->notificationManager->notify($notification);
	}

	private function isNotificationDisabledAtActivity(string $userId, string $type): bool {
		if (!class_exists(\OCA\Activity\UserSettings::class)) {
			return false;
		}
		$activityUserSettings = \OCP\Server::get(\OCA\Activity\UserSettings::class);
		if ($activityUserSettings) {
			$notificationSetting = $activityUserSettings->getUserSetting(
				$userId,
				'notification',
				$type
			);
			if (!$notificationSetting) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @psalm-return array{type: 'file', id: string, name: string, path: string, link: string}
	 */
	protected function getFileParameter(SignRequest $signRequest, FileEntity $libreSignFile): array {
		return [
			'type' => 'file',
			'id' => (string)$libreSignFile->getNodeId(),
			'name' => $libreSignFile->getName(),
			'path' => $libreSignFile->getName(),
			'link' => $this->url->linkToRouteAbsolute('libresign.page.signFPath', ['uuid' => $signRequest->getUuid(), 'path' => 'pdf']),
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
