<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Listener;

use OCA\Libresign\Db\File as FileEntity;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Events\SignRequestCanceledEvent;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\Notification\MailChannel;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationHistoryService;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\Notification\NotificationModule;
use OCA\Libresign\Service\NotificationDeliveryService;
use OCA\Libresign\Service\NotificationPreferenceResolver;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class MailNotifyListener implements NotificationModule {
	public function __construct(
		protected IUserSession $userSession,
		protected IUserManager $userManager,
		protected IdentifyService $identifyService,
		private MailChannel $mailChannel,
		private LoggerInterface $logger,
		private NotificationPreferenceResolver $notificationPreferenceResolver,
		private NotificationDeliveryService $deliveryService,
		private NotificationHistoryService $history,
	) {
	}

	/** @return list<class-string<Event>> */
	#[\Override]
	public static function supportedEvents(): array {
		return [SendSignNotificationEvent::class, SignedEvent::class, SignRequestCanceledEvent::class];
	}

	#[\Override]
	public function handle(Event $event): void {
		/** @var SendSignNotificationEvent|SignedEvent|SignRequestCanceledEvent $event */
		match ($event::class) {
			SendSignNotificationEvent::class => $this->sendSignMailNotification(
				$event->getSignRequest(),
				$event->getIdentifyMethod(),
				$event->getLibreSignFile(),
				$event->getDeliveryEventId(),
			),
			SignedEvent::class => $this->sendSignedMailNotification(
				$event->getSignRequest(),
				$event->getIdentifyMethod(),
				$event->getLibreSignFile(),
				$event->getUser(),
				$event->getDeliveryEventId(),
			),
			SignRequestCanceledEvent::class => $this->sendCanceledMailNotification(
				$event->getSignRequest(),
				$event->getIdentifyMethod(),
				$event->getLibreSignFile(),
				$event->getDeliveryEventId(),
			),
		};
	}

	protected function sendSignMailNotification(
		SignRequest $signRequest,
		IIdentifyMethod $identifyMethod,
		FileEntity $file,
		string $eventId,
	): void {
		try {
			if ($identifyMethod->getEntity()->isDeletedAccount()) {
				return;
			}
			$email = '';
			if ($identifyMethod->getName() === 'account') {
				$userId = $identifyMethod->getEntity()->getIdentifierValue();
				$email = $this->userManager->get($userId)?->getEMailAddress() ?? '';
			} elseif ($identifyMethod->getName() === 'email') {
				$email = $identifyMethod->getEntity()->getIdentifierValue();
			}
			if (empty($email)) {
				return;
			}

			$users = $this->userManager->getByEmail($email);
			if (count($users) === 1) {
				$userId = $users[0]->getUID();
				if ($this->notificationPreferenceResolver->isEmailNotificationDisabled(
					$userId,
					SendSignNotificationEvent::FILE_TO_SIGN,
					true,
				)) {
					return;
				}
			}

			$this->deliveryService->dispatch(
				new NotificationMessage(
					$eventId, 'sign_request', 'mail', $file, $signRequest, $email,
					new MailPayload(firstNotification: $this->history->isFirst($signRequest, 'mail')),
					'mail',
				),
				$this->mailChannel,
			);
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
		string $eventId,
	): void {
		try {
			if ($libreSignFile->hasParent()) {
				return;
			}
			if ($identifyMethod->getEntity()->isDeletedAccount()) {
				return;
			}
			if ($this->notificationPreferenceResolver->isEmailNotificationDisabled(
				$libreSignFile->getUserId(),
				SignedEvent::FILE_SIGNED,
				true,
			)) {
				return;
			}

			$email = $user->getEMailAddress();

			if (empty($email)) {
				return;
			}

			$this->deliveryService->dispatch(
				new NotificationMessage($eventId, 'file_signed', 'mail', $libreSignFile, $signRequest, $email, new MailPayload($user->getDisplayName())),
				$this->mailChannel,
			);

		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}

	protected function sendCanceledMailNotification(
		SignRequest $signRequest,
		IIdentifyMethod $identifyMethod,
		FileEntity $libreSignFile,
		string $eventId,
	): void {
		try {
			if ($identifyMethod->getEntity()->isDeletedAccount()) {
				return;
			}

			$email = '';
			if ($identifyMethod->getName() === 'account') {
				$userId = $identifyMethod->getEntity()->getIdentifierValue();
				$user = $this->userManager->get($userId);
				if ($user) {
					$email = $user->getEMailAddress();
				}
			} elseif ($identifyMethod->getName() === 'email') {
				$email = $identifyMethod->getEntity()->getIdentifierValue();
			}

			if (empty($email)) {
				return;
			}

			$users = $this->userManager->getByEmail($email);
			if (count($users) === 1) {
				$userId = $users[0]->getUID();
				if ($this->notificationPreferenceResolver->isEmailNotificationDisabled($userId, SignRequestCanceledEvent::SIGN_REQUEST_CANCELED, true)) {
					return;
				}
			}

			try {
				$this->deliveryService->dispatch(
					new NotificationMessage($eventId, 'sign_request_canceled', 'mail', $libreSignFile, $signRequest, $email, new MailPayload()),
					$this->mailChannel,
				);
			} catch (LibresignException) {
				// Mail failures must not undo an otherwise committed cancellation.
				return;
			}

		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return;
		}
	}
}
