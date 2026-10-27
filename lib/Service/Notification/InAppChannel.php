<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\NotificationPreparationException;
use OCP\DB\Exception as DatabaseException;
use OCP\Notification\IManager;

final class InAppChannel implements NotificationChannel {
	public function __construct(
		private IManager $manager,
		private SignRequestMapper $signRequestMapper,
	) {
	}

	#[\Override]
	public function supports(string $channel): bool {
		return $channel === 'nextcloud';
	}

	#[\Override]
	public function deliver(NotificationMessage $message): void {
		$payload = $message->payload;
		if (!$payload instanceof InAppPayload) {
			throw new \InvalidArgumentException('Invalid in-app notification payload');
		}
		if ($message->eventType === 'sign_request') {
			try {
				$isFirst = $this->signRequestMapper->incrementNotificationCounter($message->signRequest, 'notify');
			} catch (DatabaseException $e) {
				throw new NotificationPreparationException('history_write_failed', $e);
			}
			$payload->notification->setSubject($isFirst ? 'new_sign_request' : 'update_sign_request', $payload->subjectParameters);
		}
		$this->manager->notify($payload->notification);
	}
}
