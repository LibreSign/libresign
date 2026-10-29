<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\NotificationAttempt;
use OCA\Libresign\Db\NotificationAttemptMapper;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Exception\NotificationPreparationException;
use OCA\Libresign\Exception\NotificationTransportException;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationChannel;
use OCA\Libresign\Service\Notification\NotificationFailureClassifier;
use OCA\Libresign\Service\Notification\NotificationHistoryService;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\Notification\NotificationSubmissionService;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NotificationSubmissionServiceTest extends TestCase {
	private function message(): NotificationMessage {
		return new NotificationMessage('test-event', 'sign_request', 'mail', new File(), new SignRequest(), 'test@example.invalid', new MailPayload(firstNotification: true));
	}

	private function executeFailure(\Exception $failure, int $attemptNumber, NotificationDeliveryState $expected, bool $expectRetry): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(9);
		$attempt = new NotificationAttempt();
		$attempt->setId(28);
		$deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$deliveries->method('claim')->with(9)->willReturn(true);
		$deliveries->expects($this->once())->method('finish')->with(9, $expected, $this->callback(
			static fn (?\DateTime $due): bool => $expectRetry ? ($due !== null && $due > new \DateTime('now', new \DateTimeZone('UTC'))) : $due === null,
		));
		$attempts = $this->createMock(NotificationAttemptMapper::class);
		$attempts->method('begin')->with(9)->willReturn($attempt);
		$attempts->method('countForDelivery')->willReturn($attemptNumber);
		$attempts->expects($this->once())->method('finish')->with(28, $expected, $this->anything());
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->exactly(2))->method('beginTransaction');
		$db->expects($this->exactly(2))->method('commit');
		$db->expects($this->never())->method('rollBack');
		$channel = $this->createMock(NotificationChannel::class);
		$channel->method('deliver')->willThrowException($failure);
		$service = new NotificationSubmissionService($deliveries, $attempts, $db, new NotificationFailureClassifier(), $this->createMock(NotificationHistoryService::class), $this->createMock(LoggerInterface::class));
		$this->expectException(get_class($failure));
		$service->submit($delivery, $this->message(), $channel);
	}

	public function testProvenPreSubmissionFailureIsScheduled(): void {
		$this->executeFailure(new NotificationPreparationException('configuration_temporarily_unavailable'), 1, NotificationDeliveryState::TEMPORARY_FAILURE, true);
	}

	public function testRetryLimitIsTerminal(): void {
		$this->executeFailure(new NotificationPreparationException('configuration_temporarily_unavailable'), 5, NotificationDeliveryState::FAILED, false);
	}

	public function testTimeoutAfterSmtpDataIsNeverAutomaticallyRetried(): void {
		$this->executeFailure(new NotificationTransportException('synthetic timeout', NotificationDeliveryState::UNKNOWN, 'smtp_outcome_unknown'), 1, NotificationDeliveryState::UNKNOWN, false);
	}

	public function testDefinitiveRejectionIsNotRetried(): void {
		$this->executeFailure(new NotificationTransportException('synthetic rejection', NotificationDeliveryState::PERMANENT_FAILURE, 'recipient_rejected'), 1, NotificationDeliveryState::PERMANENT_FAILURE, false);
	}

	public function testNestedPreparationFailureCannotMakeTransportOutcomeSafe(): void {
		$this->executeFailure(new NotificationTransportException('synthetic uncertain outcome', NotificationDeliveryState::UNKNOWN, 'smtp_outcome_unknown', new NotificationPreparationException('preparation')), 1, NotificationDeliveryState::UNKNOWN, false);
	}
}
