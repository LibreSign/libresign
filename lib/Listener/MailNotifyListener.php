<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\MailService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event> */
class MailNotifyListener implements IEventListener {
	public function __construct(
		protected IUserSession $userSession,
		protected IUserManager $userManager,
		protected IdentifyService $identifyService,
		protected MailService $mail,
		private SignRequestMapper $signRequestMapper,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		/** @var SendSignNotificationEvent|SignedEvent $event */
		match ($event::class) {
			SendSignNotificationEvent::class => $this->sendSignMailNotification(
				$event->getSignRequest(),
				$event->getIdentifyMethod(),
			),
			SignedEvent::class => $this->sendSignedMailNotification(
				$event->getSignRequest(),
				$event->getIdentifyMethod(),
				$event->getLibreSignFile(),
				$event->getUser(),
			),
		};
	}

	protected function sendSignMailNotification(
		SignRequest $signRequest,
		IIdentifyMethod $identifyMethod,
	): void {
		try {
			if ($identifyMethod->getEntity()->isDeletedAccount()) {
				return;
			}
			if ($this->isNotificationDisabledAtActivity($identifyMethod->getEntity()->getIdentifierValue(), SendSignNotificationEvent::FILE_TO_SIGN)) {
				return;
			}
			$email = '';
			if ($identifyMethod->getName() === 'account') {
				$email = $this->userManager
					->get($identifyMethod->getEntity()->getIdentifierValue())
					->getEMailAddress();
			} elseif ($identifyMethod->getName() === 'email') {
				$email = $identifyMethod->getEntity()->getIdentifierValue();
			}
			if (empty($email)) {
				return;
			}
			$isFirstNotification = $this->signRequestMapper->incrementNotificationCounter($signRequest, 'mail');
			if ($isFirstNotification) {
				$this->mail->notifyUnsignedUser($signRequest, $email);
				return;
			}
			$this->mail->notifySignDataUpdated($signRequest, $email);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}

	protected function sendSignedMailNotification(
		SignRequest $signRequest,
		IIdentifyMethod $identifyMethod,
		FileEntity $libreSignFile,
		IUser $user,
	): void {
		try {
			if ($identifyMethod->getEntity()->isDeletedAccount()) {
				return;
			}
			if ($this->isNotificationDisabledAtActivity($libreSignFile->getUserId(), SignedEvent::FILE_SIGNED)) {
				return;
			}

			$email = $user->getEMailAddress();

			if (empty($email)) {
				return;
			}

			$this->mail->notifySignedUser($signRequest, $email, $libreSignFile, $user->getDisplayName());

		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}

	private function isNotificationDisabledAtActivity(string $userId, string $type): bool {
		if (!class_exists(\OCA\Activity\UserSettings::class)) {
			return false;
		}
		$activityUserSettings = \OCP\Server::get(\OCA\Activity\UserSettings::class);
		if ($activityUserSettings) {
			$notificationSetting = $activityUserSettings->getUserSetting(
				$userId,
				'email',
				$type
			);
			if (!$notificationSetting) {
				return true;
			}
		}
		return false;
	}
}
