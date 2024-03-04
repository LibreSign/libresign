<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
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

namespace OCA\Libresign\Activity;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\Activity\IManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {
	public function __construct(
		protected IManager $activityManager,
		protected IUserSession $userSession,
		protected LoggerInterface $logger,
		protected ITimeFactory $timeFactory,
	) {
	}

	public function handle(Event $event): void {
		match (get_class($event)) {
			SendSignNotificationEvent::class => $this->generateNewSignNotificationActivity(
				$event->getSignRequest(),
				$event->getIdentifyMethod(),
				$event->isNew()
			),
		};
	}

	/**
	 * Invitation activity: "{actor} invited you to {call}"
	 *
	 * @param Room $room
	 * @param Attendee[] $attendees
	 */
	protected function generateNewSignNotificationActivity(
		SignRequest $signRequest,
		IIdentifyMethod $identifyMethod,
		bool $isNew
	): void {
		$actor = $this->userSession->getUser();
		if (!$actor instanceof IUser) {
			return;
		}
		$actorId = $actor->getUID();

		$event = $this->activityManager->generateEvent();
		try {
			$event
				->setApp(Application::APP_ID)
				->setType(Application::APP_ID)
				->setAuthor($actorId)
				->setObject('sign', $signRequest->getId(), 'signRequest')
				->setTimestamp($this->timeFactory->getTime())
				->setAffectedUser($identifyMethod->getEntity()->getIdentifierValue());
			if ($isNew) {
				$event->setSubject('new_sign_request', [
					'from' => $actor->getUID(),
					'signer' => $identifyMethod->getEntity()->getIdentifierValue(),
					'signRequest' => $signRequest->getId(),
				]);
			} else {
				$event->setSubject('update_sign_request', [
					'from' => $actor->getUID(),
					'signer' => $identifyMethod->getEntity()->getIdentifierValue(),
					'signRequest' => $signRequest->getId(),
				]);
			}
			$this->activityManager->publish($event);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}
}
