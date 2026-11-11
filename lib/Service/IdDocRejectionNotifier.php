<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\IdDocs;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\Notification\IdDocRejectionPayload;
use OCA\Libresign\Service\Notification\InAppChannel;
use OCA\Libresign\Service\Notification\InAppPayload;
use OCA\Libresign\Service\Notification\MailChannel;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Notification\IManager;

/** Register rejection notification intents inside the caller's transaction. */
class IdDocRejectionNotifier {
	public function __construct(
		private IdDocRejectionRecipientResolver $recipients,
		private SignRequestMapper $signRequests,
		private ITimeFactory $clock,
		private NotificationDeliveryService $notifications,
		private MailChannel $mail,
		private InAppChannel $inApp,
		private IManager $notificationManager,
	) {
	}

	public function queue(IdDocs $document, File $file, string $reason): void {
		$signRequestId = $document->getSignRequestId();
		if ($signRequestId === null) {
			return;
		}

		$signRequest = $this->signRequests->getById($signRequestId);
		$eventId = 'id-doc-rejected:' . $document->getId();

		foreach ($this->recipients->resolve($document->getUserId(), $signRequestId) as $recipient) {
			$email = $recipient['email'];
			$uid = $recipient['uid'];
			if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$this->notifications->dispatch(new NotificationMessage(
					$eventId, 'id_doc_rejected', 'mail', $file, $signRequest, $email,
					new IdDocRejectionPayload($reason, $uid !== null, $uid),
				), $this->mail);
			}
			if (!is_string($uid) || $uid === '') {
				continue;
			}

			$notification = $this->notificationManager->createNotification()
				->setApp(Application::APP_ID)
				->setUser($uid)
				->setObject('identificationDocument', (string)$document->getId())
				->setDateTime($this->clock->getDateTime())
				->setSubject('identification_document_rejected', [
					'reason' => $reason,
					'documentName' => $file->getName(),
				]);
			$this->notifications->dispatch(new NotificationMessage(
				$eventId, 'id_doc_rejected', 'nextcloud', $file, $signRequest, $uid,
				new InAppPayload($notification),
			), $this->inApp);
		}
	}

	/** Deliver only after the caller's rejection transaction has committed. */
	public function flushCommitted(): void {
		$this->notifications->flushCommitted();
	}
}
