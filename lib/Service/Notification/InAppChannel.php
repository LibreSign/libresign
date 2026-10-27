<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCP\Notification\IManager;

final class InAppChannel implements NotificationChannel {
	public function __construct(
		private IManager $manager,
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
		$this->manager->notify($payload->notification);
	}
}
