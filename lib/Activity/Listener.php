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
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\Activity\IManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IURLGenerator;
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
		protected AccountService $accountService,
		protected IURLGenerator $url,
	) {
	}

	public function handle(Event $event): void {
		/** @var SendSignNotificationEvent $event */
		match (get_class($event)) {
			SendSignNotificationEvent::class => $this->generateNewSignNotificationActivity(
				$event->getSignRequest(),
				$event->getLibreSignFile(),
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
		FileEntity $libreSignFile,
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
				->setType('file_to_sign')
				->setAuthor($actorId)
				->setObject('sign', $signRequest->getId(), 'signRequest')
				->setTimestamp($this->timeFactory->getTime())
				->setAffectedUser($identifyMethod->getEntity()->getIdentifierValue());
			if ($isNew) {
				$subject = 'new_sign_request';
			} else {
				$subject = 'update_sign_request';
			}
			$event->setSubject($subject, [
				'from' => $this->getUserParameter(
					$actor->getUID(),
					$actor->getDisplayName(),
				),
				'file' => $this->getFileParameter($signRequest, $libreSignFile),
				'signer' => $this->getUserParameter(
					$identifyMethod->getEntity()->getIdentifierValue(),
					$signRequest->getDisplayName(),
				),
			]);
			$this->activityManager->publish($event);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
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
