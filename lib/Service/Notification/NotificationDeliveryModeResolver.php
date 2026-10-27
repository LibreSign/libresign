<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\NotificationDelivery\NotificationDeliveryModePolicy;

/** Read the dedicated notification policy, independently of signing workers. */
class NotificationDeliveryModeResolver {
	public function __construct(
		private PolicyService $policies,
	) {
	}

	public function isAsync(): bool {
		return $this->policies->resolve(NotificationDeliveryModePolicy::KEY)->getEffectiveValue()
			=== NotificationDeliveryModePolicy::MODE_ASYNC;
	}
}
