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
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Exception\NotificationPreparationException;
use OCA\Libresign\Exception\NotificationTransportException;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationChannel;
use OCA\Libresign\Service\Notification\NotificationFailureClassifier;
use OCA\Libresign\Service\Notification\NotificationHistoryService;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\Notification\NotificationSubmissionService;
use OCA\Libresign\Service\Webhook\NativeWebhookEventPublisher;
use OCP\IDBConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NotificationSubmissionServiceTest extends TestCase {
	private function message(): NotificationMessage {
		return new NotificationMessage('test-event', 'sign_request', 'mail', new File(), new SignRequest(), 'test@example.invalid', new MailPayload(firstNotification: true));
	}

	private function service(
		NotificationDeliveryMapper $deliveries,
		NotificationAttemptMapper $attempts,
		NativeWebhookEventPublisher $publisher,
		?IDBConnection $db = null,
	): NotificationSubmissionService {
		return new NotificationSubmissionService(
			$deliveries,
			$attempts,
			$db ?? $this->createMock(IDBConnection::class),
			new NotificationFailureClassifier(),
			new NotificationHistoryService($this->createMock(SignRequestMapper::class)),
			$this->createMock(LoggerInterface::class),
			$publisher,
		);
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
		$publisher = $this->createMock(NativeWebhookEventPublisher::class);
		$publisher->expects($this->once())->method('notificationOutcome')->with(9);
		$service = $this->service($deliveries, $attempts, $publisher, $db);
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

	public function testPublishedOutcomeFollowsFinalLedgerCommit(): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(9);
		$attempt = new NotificationAttempt();
		$attempt->setId(28);
		$deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$deliveries->method('claim')->willReturn(true);
		$attempts = $this->createMock(NotificationAttemptMapper::class);
		$attempts->method('begin')->willReturn($attempt);

		$commits = 0;
		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->exactly(2))->method('commit')
			->willReturnCallback(static function () use (&$commits): void {
				$commits++;
			});
		$channel = $this->createMock(NotificationChannel::class);
		$channel->expects($this->once())->method('deliver')
			->willReturnCallback(function () use (&$commits): void {
				$this->assertSame(1, $commits, 'The attempt must be claimed before sending.');
			});
		$publisher = $this->createMock(NativeWebhookEventPublisher::class);
		$publisher->expects($this->once())->method('notificationOutcome')->with(9)
			->willReturnCallback(function () use (&$commits): void {
				$this->assertSame(2, $commits, 'Publication must follow the committed ledger outcome.');
			});

		$this->assertTrue($this->service($deliveries, $attempts, $publisher, $db)
			->submit($delivery, $this->message(), $channel));
	}

	public function testFailedLedgerPersistenceDoesNotPublishOutcome(): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(9);
		$attempt = new NotificationAttempt();
		$attempt->setId(28);
		$deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$deliveries->method('claim')->willReturn(true);
		$attempts = $this->createMock(NotificationAttemptMapper::class);
		$attempts->method('begin')->willReturn($attempt);
		$attempts->method('finish')->willThrowException(new \RuntimeException('ledger write failed'));

		$db = $this->createMock(IDBConnection::class);
		$db->expects($this->once())->method('rollBack');
		$publisher = $this->createMock(NativeWebhookEventPublisher::class);
		$publisher->expects($this->never())->method('notificationOutcome');
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('ledger write failed');

		$this->service($deliveries, $attempts, $publisher, $db)->submit(
			$delivery,
			$this->message(),
			$this->createMock(NotificationChannel::class),
		);
	}

	public function testAlreadyClaimedDeliveryNeverSubmitsOrPublishes(): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(9);
		$deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$deliveries->method('claim')->willReturn(false);
		$channel = $this->createMock(NotificationChannel::class);
		$channel->expects($this->never())->method('deliver');
		$publisher = $this->createMock(NativeWebhookEventPublisher::class);
		$publisher->expects($this->never())->method('notificationOutcome');
		$this->assertFalse($this->service(
			$deliveries,
			$this->createMock(NotificationAttemptMapper::class),
			$publisher,
		)->submit($delivery, $this->message(), $channel));
	}

}
