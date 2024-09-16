<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\AppInfo\Application as AppInfoApplication;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Events\SendSignNotificationEvent;
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
		if ($this->isNotificationDisabledAtActivity($identifyMethod)) {
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

	public function isNotificationDisabledAtActivity(IIdentifyMethod $identifyMethod): bool {
		if (!class_exists(\OCA\Activity\UserSettings::class)) {
			return false;
		}
		$activityUserSettings = \OCP\Server::get(\OCA\Activity\UserSettings::class);
		if ($activityUserSettings) {
			if ($identifyMethod->getEntity()->isDeletedAccount()) {
				return false;
			}
			$notificationSetting = $activityUserSettings->getUserSetting(
				$identifyMethod->getEntity()->getIdentifierValue(),
				'notification',
				'file_to_sign'
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
}
