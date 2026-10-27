<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

final readonly class GatewayPayload implements NotificationPayload {
	public function __construct(
		public string $gatewayName,
		public string $body,
	) {
	}
}
