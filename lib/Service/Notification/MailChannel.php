<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Service\MailService;

final class MailChannel implements NotificationChannel {
	public function __construct(
		private MailService $mail,
	) {
	}

	#[\Override]
	public function supports(string $channel): bool {
		return $channel === 'mail';
	}

	#[\Override]
	public function deliver(NotificationMessage $message): void {
		if (!$message->payload instanceof MailPayload) {
			throw new \InvalidArgumentException('Invalid mail notification payload');
		}
		$signRequest = $message->signRequest;
		$email = $message->recipient;
		switch ($message->eventType) {
			case 'sign_request':
				if ($message->payload->firstNotification) {
					$this->mail->notifyUnsignedUser($signRequest, $email, $signRequest->getDescription());
				} else {
					$this->mail->notifySignDataUpdated($signRequest, $email, $signRequest->getDescription());
				}
				break;
			case 'file_signed':
				$this->mail->notifySignedUser($signRequest, $email, $message->file, $message->payload->displayName ?? '');
				break;
			case 'sign_request_canceled':
				$this->mail->notifyCanceledRequest($signRequest, $email, $message->file);
				break;
			default:
				throw new \InvalidArgumentException('Unsupported mail notification event');
		}
	}
}
