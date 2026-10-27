<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Notification;

use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Exception\NotificationPreparationException;
use OCA\Libresign\Exception\NotificationTransportException;

final class NotificationFailureClassifier {
	/** @return array{NotificationDeliveryState, string} */
	public function classify(\Exception $error): array {
		for ($current = $error; $current !== null; $current = $current->getPrevious()) {
			if ($current instanceof NotificationPreparationException) {
				return [NotificationDeliveryState::TEMPORARY_FAILURE, $current->reasonCode];
			}
			if ($current instanceof NotificationTransportException) {
				return [$current->outcome, $current->reasonCode];
			}
		}
		return [NotificationDeliveryState::UNKNOWN, 'transport_outcome_unknown'];
	}
}
