<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Exception\NotificationPreparationException;
use OCA\Libresign\Exception\NotificationTransportException;
use OCA\Libresign\Service\Notification\NotificationFailureClassifier;
use PHPUnit\Framework\TestCase;

final class NotificationFailureClassifierTest extends TestCase {
	public function testDirectPreparationFailureProvesNoTransportSubmission(): void {
		$this->assertSame(
			[NotificationDeliveryState::TEMPORARY_FAILURE, 'before_provider'],
			(new NotificationFailureClassifier())->classify(new NotificationPreparationException('before_provider')),
		);
	}

	public function testNestedPreparationFailureIsNotEvidenceOfNoSubmission(): void {
		$this->assertSame(
			[NotificationDeliveryState::UNKNOWN, 'transport_outcome_unknown'],
			(new NotificationFailureClassifier())->classify(new \RuntimeException('synthetic uncertain', 0, new NotificationPreparationException('before_provider'))),
		);
	}

	public function testTransportTemporaryLabelAloneIsNotSafeForReplay(): void {
		$this->assertSame(
			[NotificationDeliveryState::UNKNOWN, 'transport_outcome_unknown'],
			(new NotificationFailureClassifier())->classify(new NotificationTransportException('synthetic uncertain', NotificationDeliveryState::TEMPORARY_FAILURE, 'temporary')),
		);
	}

	public function testPermanentRejectionKeepsItsOutcome(): void {
		$this->assertSame(
			[NotificationDeliveryState::PERMANENT_FAILURE, 'recipient_rejected'],
			(new NotificationFailureClassifier())->classify(new NotificationTransportException('synthetic rejected', NotificationDeliveryState::PERMANENT_FAILURE, 'recipient_rejected')),
		);
	}
}
