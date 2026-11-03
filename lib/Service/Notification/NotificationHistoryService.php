<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;

/**
 * Backward-compatible projection for reminders.
 * Only an accepted channel submission counts as a delivered notification.
 */
final class NotificationHistoryService {
	public function __construct(
		private SignRequestMapper $signRequestMapper,
	) {
	}

	public function isFirst(SignRequest $request, string $method): bool {
		$metadata = $this->signRequestMapper->getByIdUncached($request->getId())->getMetadata() ?? [];
		foreach ($metadata['notify'] ?? [] as $item) {
			if (($item['method'] ?? null) === $method) {
				return false;
			}
		}
		return true;
	}

	public function recordAccepted(NotificationMessage $message): void {
		if ($message->eventType === 'sign_request' && $message->historyMethod !== null) {
			$this->signRequestMapper->incrementNotificationCounter($message->signRequest, $message->historyMethod);
		}
	}
}
