<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

/** The caller selects the business event; the mail adapter selects its template. */
final readonly class MailPayload implements NotificationPayload {
	/** @param list<int> $documentIds Documents attached to a signed copy mail. */
	public function __construct(
		public ?string $displayName = null,
		public bool $firstNotification = false,
		public array $documentIds = [],
	) {
	}
}
