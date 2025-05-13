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
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<SendSignNotificationEvent> */
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
		/** @var SendSignNotificationEvent $event */
		match (get_class($event)) {
			SendSignNotificationEvent::class => $this->sendSignMailNotification(
				$event->getSignRequest(),
				$event->getLibreSignFile(),
				$event->getIdentifyMethod(),
			),
			SignedEvent::class => $this->sendSignedMailNotification(
				$event->getSignRequest(),
				$event->getLibreSignFile(),
				$event->getIdentifyMethod(),
			),
		};
	}

	protected function sendSignMailNotification(
		SignRequest $signRequest,
		FileEntity $libreSignFile,
		IIdentifyMethod $identifyMethod,
	): void {
		try {
			if ($identifyMethod->getEntity()->isDeletedAccount()) {
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
		FileEntity $libreSignFile,
		IIdentifyMethod $identifyMethod,
	): void {
		try {
			if ($this->isNotificationDisabledAtActivity($identifyMethod, 'file_signed')) {
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

			$this->mail->notifySignedUser($signRequest, $email);

		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}

	public function isNotificationDisabledAtActivity(IIdentifyMethod $identifyMethod, string $type): bool {
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
