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
use Psr\Log\LoggerInterface;

class MailNotifyListener implements NotificationModule {
	public function __construct(
		protected IUserManager $userManager,
		private MailChannel $mailChannel,
		private LoggerInterface $logger,
		private NotificationPreferenceResolver $notificationPreferenceResolver,
		private NotificationDeliveryService $deliveryService,
		private NotificationHistoryService $history,
	) {
	}

	#[\Override]
	public function transport(): \OCA\Libresign\Service\Notification\NotificationChannel {
		return $this->mailChannel;
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
			$email = $this->getRecipientEmail($identifyMethod, SendSignNotificationEvent::FILE_TO_SIGN);
			if ($email === null) {
				return;
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
			$email = $this->getRecipientEmail($identifyMethod, SignRequestCanceledEvent::SIGN_REQUEST_CANCELED);
			if ($email === null) {
				return;
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

	private function getRecipientEmail(IIdentifyMethod $identifyMethod, string $notificationType): ?string {
		$entity = $identifyMethod->getEntity();
		if ($entity->isDeletedAccount()) {
			return null;
		}

		if ($identifyMethod->getName() === 'email') {
			$email = $entity->getIdentifierValue();
			return $email !== '' ? $email : null;
		}
		if ($identifyMethod->getName() !== 'account') {
			return null;
		}

		$user = $this->userManager->get($entity->getIdentifierValue());
		if (!$user instanceof IUser) {
			return null;
		}
		$email = $user->getEMailAddress();
		if (empty($email) || $this->notificationPreferenceResolver->isEmailNotificationDisabled(
			$user->getUID(),
			$notificationType,
			true,
		)) {
			return null;
		}
		return $email;
	}
}
