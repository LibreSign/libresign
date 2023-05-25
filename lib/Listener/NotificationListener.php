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
use OCA\Libresign\Db\FileUser;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Notification\IManager;

/**
 * @template-implements IEventListener<Event>
 */
class NotificationListener implements IEventListener {
	public function __construct(
		private IManager $notificationManager,
		private ITimeFactory $timeFactory
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof SendSignNotificationEvent) {
			$this->sendNewSignNotification(
				$event->getFileUser(),
				$event->getIdentifyMethod(),
				$event->isNew()
			);
		}
	}

	private function sendNewSignNotification(
		FileUser $fileUser,
		IIdentifyMethod $identifyMethod,
		bool $isNew
	): void {
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp(AppInfoApplication::APP_ID)
			->setObject('identifyMethod', (string) $identifyMethod->getEntity()->getId())
			->setDateTime((new \DateTime())->setTimestamp($this->timeFactory->now()->getTimestamp()))
			->setUser($identifyMethod->getEntity()->getIdentifierValue());
		if ($isNew) {
			$notification->setSubject('new_sign_request', [
				'fileUser' => $fileUser->getId(),
			]);
		} else {
			$notification->setSubject('update_sign_request', [
				'fileUser' => $fileUser->getId(),
			]);
		}
		$this->notificationManager->notify($notification);
	}
}
