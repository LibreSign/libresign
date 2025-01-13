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
			SendSignNotificationEvent::class => $this->sendMailNotification(
				$event->getSignRequest(),
				$event->getLibreSignFile(),
				$event->getIdentifyMethod(),
			),
		};
	}

	protected function sendMailNotification(
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
}
