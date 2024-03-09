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
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof SendSignNotificationEvent) {
			$this->sendNewSignNotification(
				$event->getSignRequest(),
				$event->getLibreSignFile(),
				$event->getIdentifyMethod(),
				$event->isNew()
			);
		}
	}

	private function sendNewSignNotification(
		SignRequest $signRequest,
		FileEntity $libreSignFile,
		IIdentifyMethod $identifyMethod,
		bool $isNew
	): void {
		$actor = $this->userSession->getUser();
		if (!$actor instanceof IUser) {
			return;
		}
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp(AppInfoApplication::APP_ID)
			->setObject('signRequest', (string) $signRequest->getId())
			->setDateTime((new \DateTime())->setTimestamp($this->timeFactory->now()->getTimestamp()))
			->setUser($identifyMethod->getEntity()->getIdentifierValue());
		if ($isNew) {
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
				'id' => $signRequest->getId(),
				'name' => $actor->getDisplayName(),
			],
		]);
		$this->notificationManager->notify($notification);
	}

	protected function getFileParameter(SignRequest $signRequest, FileEntity $libreSignFile) {
		return [
			'type' => 'file',
			'id' => $libreSignFile->getNodeId(),
			'name' => $libreSignFile->getName(),
			'path' => $libreSignFile->getName(),
			'link' => $this->url->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $signRequest->getUuid()]),
		];
	}

	protected function getUserParameter(
		string $userId,
		$displayName,
	): array {
		return [
			'type' => 'user',
			'id' => $userId,
			'name' => $displayName,
		];
	}
}
