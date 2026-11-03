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
		// Only an explicit top-level preparation failure proves the provider
		// was not contacted. Nested causes can come from an ambiguous transport.
		if ($error instanceof NotificationPreparationException) {
			return [NotificationDeliveryState::TEMPORARY_FAILURE, $error->reasonCode];
		}
		for ($current = $error; $current !== null; $current = $current->getPrevious()) {
			if ($current instanceof NotificationTransportException) {
				// A transport exception alone never proves non-submission.
				if ($current->outcome === NotificationDeliveryState::TEMPORARY_FAILURE) {
					return [NotificationDeliveryState::UNKNOWN, 'transport_outcome_unknown'];
				}
				return [$current->outcome, $current->reasonCode];
			}
		}
		return [NotificationDeliveryState::UNKNOWN, 'transport_outcome_unknown'];
	}
}
