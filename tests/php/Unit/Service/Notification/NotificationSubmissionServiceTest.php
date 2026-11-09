<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\FileMapper;
use OCA\Libresign\Db\NotificationAttempt;
use OCA\Libresign\Db\NotificationAttemptMapper;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Events\NotificationDeliveryStatusChangedEvent;
use OCA\Libresign\Exception\NotificationPreparationException;
use OCA\Libresign\Exception\NotificationTransportException;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationChannel;
use OCA\Libresign\Service\Notification\NotificationFailureClassifier;
use OCA\Libresign\Service\Notification\NotificationHistoryService;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\Notification\NotificationSubmissionService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NotificationSubmissionServiceTest extends TestCase {
	private function message(): NotificationMessage {
		return new NotificationMessage('test-event', 'sign_request', 'mail', new File(), new SignRequest(), 'test@example.invalid', new MailPayload(firstNotification: true));
	}

	private function executeFailure(\Exception $failure, int $attemptNumber, NotificationDeliveryState $expected, bool $expectRetry): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(9);
		$delivery->setAttemptCount($attemptNumber);
		$attempt = new NotificationAttempt();
		$attempt->setId(28);
		$deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$deliveries->method('claim')->with(9)->willReturn(true);
		$deliveries->method('findById')->with(9)->willReturn($delivery);
		$deliveries->expects($this->once())->method('finish')->with(9, $expected, $this->callback(
			static function (?\DateTime $due) use ($expectRetry, $attemptNumber): bool {
				if (!$expectRetry) {
					return $due === null;
				}
				if ($due === null) {
					return false;
				}
				$seconds = $due->getTimestamp() - time();
				$base = min(3600, 30 * (2 ** ($attemptNumber - 1)));
				return $seconds >= $base - 1 && $seconds <= $base + min(30, $base) + 1;
			},
		));
		$attempts = $this->createMock(NotificationAttemptMapper::class);
		$attempts->method('begin')->with(9)->willReturn($attempt);
		$attempts->expects($this->once())->method('finish')->with(28, $expected, $this->anything());
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->exactly(2))->method('beginTransaction');
		$db->expects($this->exactly(2))->method('commit');
		$db->expects($this->never())->method('rollBack');
		$channel = $this->createMock(NotificationChannel::class);
		$channel->method('deliver')->willThrowException($failure);
		$service = new NotificationSubmissionService($deliveries, $attempts, $db, new NotificationFailureClassifier(), new NotificationHistoryService($this->createMock(SignRequestMapper::class)), $this->createMock(LoggerInterface::class), $this->createMock(FileMapper::class), $this->createMock(IEventDispatcher::class));
		$this->expectException(get_class($failure));
		$service->submit($delivery, $this->message(), $channel);
	}

	/** @return array<string, array{int}> */
	public static function safeAttemptNumbers(): array {
		return ['initial' => [1], 'second' => [2], 'third' => [3], 'fourth' => [4]];
	}

	#[DataProvider('safeAttemptNumbers')]
	public function testProvenPreSubmissionFailureIsScheduled(int $attemptNumber): void {
		$this->executeFailure(new NotificationPreparationException('configuration_temporarily_unavailable'), $attemptNumber, NotificationDeliveryState::TEMPORARY_FAILURE, true);
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

	public function testTransportTemporaryFailureWithoutProofIsUnknown(): void {
		$this->executeFailure(new NotificationTransportException('synthetic temporary', NotificationDeliveryState::TEMPORARY_FAILURE, 'temporary'), 1, NotificationDeliveryState::UNKNOWN, false);
	}

	public function testGenericTransportWrapperCannotMakeNestedPreparationSafe(): void {
		$this->executeFailure(new \RuntimeException('synthetic uncertain transport', 0, new NotificationPreparationException('preparation')), 1, NotificationDeliveryState::UNKNOWN, false);
	}

	public function testNestedPreparationFailureCannotMakeTransportOutcomeSafe(): void {
		$this->executeFailure(new NotificationTransportException('synthetic uncertain outcome', NotificationDeliveryState::UNKNOWN, 'smtp_outcome_unknown', new NotificationPreparationException('preparation')), 1, NotificationDeliveryState::UNKNOWN, false);
	}

	public function testAcceptedOutcomeWebhookIsDispatchedOnlyAfterLedgerCommit(): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(9);
		$delivery->setFileId(25);
		$delivery->setChannel('mail');
		$delivery->setAttemptCount(1);
		$persisted = new NotificationDelivery();
		$persisted->setId(9);
		$persisted->setFileId(25);
		$persisted->setChannel('mail');
		$persisted->setAttemptCount(1);
		$persisted->setState(NotificationDeliveryState::ACCEPTED->value);
		$attempt = new NotificationAttempt();
		$attempt->setId(28);

		$deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$deliveries->method('claim')->with(9)->willReturn(true);
		$deliveries->method('findById')->with(9)->willReturn($persisted);
		$deliveries->expects($this->once())->method('finish')->with(9, NotificationDeliveryState::ACCEPTED, null);
		$attempts = $this->createMock(NotificationAttemptMapper::class);
		$attempts->method('begin')->willReturn($attempt);
		$attempts->expects($this->once())->method('finish')->with(28, NotificationDeliveryState::ACCEPTED, null);
		$db = $this->createMock(IDBConnection::class);
		$committed = 0;
		$db->expects($this->exactly(2))->method('commit')->willReturnCallback(static function () use (&$committed): void {
			$committed++;
		});
		$root = new File();
		$root->setUuid('public-workflow-uuid');
		$files = $this->createMock(FileMapper::class);
		$files->method('getById')->with(25)->willReturn($root);
		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher->expects($this->once())->method('dispatchTyped')->with($this->callback(
			static function ($event) use (&$committed): bool {
				return $committed === 2
					&& $event instanceof NotificationDeliveryStatusChangedEvent
					&& $event->getWebhookSerializable()['state'] === 'accepted'
					&& $event->getWebhookSerializable()['requestUuid'] === 'public-workflow-uuid';
			},
		));
		$channel = $this->createMock(NotificationChannel::class);
		$channel->expects($this->once())->method('deliver');
		$history = new NotificationHistoryService($this->createMock(SignRequestMapper::class));
		$service = new NotificationSubmissionService(
			$deliveries, $attempts, $db, new NotificationFailureClassifier(),
			$history, $this->createMock(LoggerInterface::class), $files, $dispatcher,
		);
		$this->assertTrue($service->submit($delivery, $this->message(), $channel));
	}

}
