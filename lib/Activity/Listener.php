<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Activity;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Service\AccountService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCP\Activity\Exceptions\UnknownActivityException;
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
		private SignRequestMapper $signRequestMapper,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		/** @var SendSignNotificationEvent|SignedEvent $event */
		match ($event::class) {
			SendSignNotificationEvent::class => $this->generateNewSignNotificationActivity(
				$event->getSignRequest(),
				$event->getLibreSignFile(),
				$event->getIdentifyMethod(),
			),
			SignedEvent::class => $this->generateSignedEventActivity(
				$event->getSignRequest(),
				$event->getLibreSignFile(),
				$event->getIdentifyMethod(),
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
				->setType(SendSignNotificationEvent::FILE_TO_SIGN)
				->setAuthor($actorId)
				->setObject('signRequest', $signRequest->getId())
				->setTimestamp($this->timeFactory->getTime())
				->setAffectedUser($identifyMethod->getEntity()->getIdentifierValue())
				// Activity notification was replaced by Notification app
				// At notification app we can define the view and dismiss action
				// Activity dont have this feature
				->setGenerateNotification(false);
			$isFirstNotification = $this->signRequestMapper->incrementNotificationCounter($signRequest, 'activity');
			if ($isFirstNotification) {
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
				'signRequest' => [
					'type' => 'sign-request',
					'id' => (string)$signRequest->getId(),
					'name' => $actor->getDisplayName(),
				],
			]);
			$this->activityManager->publish($event);
		} catch (UnknownActivityException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}

	protected function generateSignedEventActivity(
		SignRequest $signRequest,
		FileEntity $libreSignFile,
		IIdentifyMethod $identifyMethod,
	): void {

		$actorId = $libreSignFile->getUserId();

		$activityEvent = $this->activityManager->generateEvent();

		try {
			$activityEvent
				->setApp(Application::APP_ID)
				->setType(SignedEvent::FILE_SIGNED)
				->setAuthor($actorId)
				->setObject('signedFile', 10)
				->setTimestamp($this->timeFactory->getTime())
				->setAffectedUser($actorId)
				->setGenerateNotification(true);

			$activityEvent->setSubject('file_signed', [
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
				'sign-request' => [
					'type' => 'sign-request',
					'id' => (string)$signRequest->getId(),
					'name' => $signRequest->getDisplayName(),
				],
			]);

			$this->activityManager->publish($activityEvent);
		} catch (UnknownActivityException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}

	/**
	 * @return array{type: 'file', id: string, name: string, path: string, link: string}
	 */
	protected function getFileParameter(SignRequest $signRequest, FileEntity $libreSignFile): array {
		return [
			'type' => 'file',
			'id' => (string)$libreSignFile->getNodeId(),
			'name' => $libreSignFile->getName(),
			'path' => $libreSignFile->getName(),
			'link' => $this->url->linkToRouteAbsolute('libresign.page.sign', ['uuid' => $signRequest->getUuid()]),
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
