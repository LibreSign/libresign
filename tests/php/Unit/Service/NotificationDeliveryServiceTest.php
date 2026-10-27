<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\NotificationAttempt;
use OCA\Libresign\Db\NotificationAttemptMapper;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Exception\MailTransportException;
use OCA\Libresign\Exception\NotificationPreparationException;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationChannel;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\NotificationDeliveryService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Security\ICrypto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NotificationDeliveryServiceTest extends TestCase {
	private NotificationDeliveryMapper&MockObject $deliveries;
	private NotificationAttemptMapper&MockObject $attempts;
	private IDBConnection&MockObject $db;
	private NotificationDeliveryService $service;
	private File $file;
	private SignRequest $request;

	protected function setUp(): void {
		$this->deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$this->attempts = $this->createMock(NotificationAttemptMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->with('signer@example.invalid')->willReturn('encrypted-mail');
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')->with('secret')->willReturn('a-synthetic-instance-secret');
		$this->service = new NotificationDeliveryService(
			$this->deliveries,
			$this->attempts,
			$this->db,
			$crypto,
			$config,
			$this->createMock(LoggerInterface::class),
		);
		$this->file = new File();
		$this->file->setId(12);
		$this->file->setUserId('owner');
		$this->request = new SignRequest();
		$this->request->setId(23);
	}

	private function send(
		string $eventId,
		string $eventType,
		File $file,
		SignRequest $request,
		string $recipient,
		callable $send,
	): bool {
		$channel = new class(\Closure::fromCallable($send)) implements NotificationChannel {
			public function __construct(
				private \Closure $callback,
			) {
			}
			public function supports(string $channel): bool {
				return $channel === 'mail';
			}
			public function deliver(NotificationMessage $message): void {
				($this->callback)();
			}
		};
		return $this->service->dispatch(new NotificationMessage(
			$eventId, $eventType, 'mail', $file, $request, $recipient, new MailPayload(),
		), $channel);
	}

	private function expectFreshDelivery(): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(31);
		$attempt = new NotificationAttempt();
		$attempt->setId(41);
		$this->deliveries->method('register')->willReturn($delivery);
		$this->deliveries->method('claim')->with(31)->willReturn(true);
		$this->attempts->method('begin')->with(31)->willReturn($attempt);
	}

	public function testSendsOnlyAfterClaimAndRecordsTransportAcceptance(): void {
		$calls = [];
		$this->expectFreshDelivery();
		$this->attempts->expects($this->once())->method('finish')
			->with(41, NotificationDeliveryState::ACCEPTED, null)
			->willReturnCallback(static function () use (&$calls): void {
				$calls[] = 'finish';
			});
		$this->deliveries->expects($this->once())->method('finish')
			->with(31, NotificationDeliveryState::ACCEPTED);
		$this->db->expects($this->exactly(2))->method('beginTransaction');
		$this->db->expects($this->exactly(2))->method('commit');

		$called = $this->send('source-1', 'sign_request', $this->file, $this->request, 'signer@example.invalid', static function () use (&$calls): void {
			$calls[] = 'send';
		});

		$this->assertTrue($called);
		$this->assertSame(['send', 'finish'], $calls);
	}

	public function testExistingAcceptedEventNeverRunsSmtp(): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(31);
		$this->deliveries->method('register')->willReturn($delivery);
		$this->deliveries->method('claim')->with(31)->willReturn(false);
		$this->attempts->expects($this->never())->method('begin');

		$this->assertFalse($this->send(
			'same-source', 'sign_request', $this->file, $this->request, 'signer@example.invalid',
			static function (): void {
				throw new \LogicException('A completed event must not send another email');
			},
		));
	}

	public function testExistingPendingEventCanBeClaimedAfterCrashBeforeSending(): void {
		// A PENDING record means there was no SMTP submission yet.
		$delivery = new NotificationDelivery();
		$delivery->setId(31);
		$attempt = new NotificationAttempt();
		$attempt->setId(41);
		$this->deliveries->method('register')->willReturn($delivery);
		$this->deliveries->method('claim')->willReturn(true);
		$this->attempts->method('begin')->willReturn($attempt);
		$this->attempts->expects($this->once())->method('finish')
			->with(41, NotificationDeliveryState::ACCEPTED, null);
		$sent = 0;
		$this->assertTrue($this->send(
			'same-source', 'sign_request', $this->file, $this->request, 'signer@example.invalid',
			static function () use (&$sent): void {
				$sent++;
			},
		));
		$this->assertSame(1, $sent);
	}

	public function testConcurrentDispatcherCannotClaimTheSameDelivery(): void {
		$delivery = new NotificationDelivery();
		$delivery->setId(31);
		$this->deliveries->method('register')->willReturn($delivery);
		$this->deliveries->method('claim')->willReturn(false);
		$this->attempts->expects($this->never())->method('begin');

		$this->assertFalse($this->send(
			'race-1', 'sign_request', $this->file, $this->request, 'signer@example.invalid',
			static function (): void {
				throw new \LogicException('Lost claim must not reach SMTP');
			},
		));
	}

	public function testReturnedFailedRecipientIsNotReportedAsAccepted(): void {
		$this->expectFreshDelivery();
		$this->attempts->expects($this->once())->method('finish')
			->with(41, NotificationDeliveryState::FAILED, 'recipient_rejected_unclassified');
		$this->deliveries->expects($this->once())->method('finish')
			->with(31, NotificationDeliveryState::FAILED);

		$this->expectException(MailTransportException::class);
		$this->send('failure-1', 'sign_request', $this->file, $this->request, 'signer@example.invalid',
			static function (): void {
				throw new MailTransportException('Recipient rejected', NotificationDeliveryState::FAILED, 'recipient_rejected_unclassified');
			},
		);
	}

	public function testHistoryPersistenceFailureDoesNotReachTransport(): void {
		$this->expectFreshDelivery();
		$this->attempts->expects($this->once())->method('finish')
			->with(41, NotificationDeliveryState::TEMPORARY_FAILURE, 'history_write_failed');
		$this->deliveries->expects($this->once())->method('finish')
			->with(31, NotificationDeliveryState::TEMPORARY_FAILURE);

		$this->expectException(NotificationPreparationException::class);
		$this->send(
			'history-failed', 'sign_request', $this->file, $this->request, 'signer@example.invalid',
			static function (): void {
				throw new NotificationPreparationException('history_write_failed');
			},
		);
	}

	public function testTimeoutAfterSmtpSubmissionBecomesUnknown(): void {
		$this->expectFreshDelivery();
		$this->attempts->expects($this->once())->method('finish')
			->with(41, NotificationDeliveryState::UNKNOWN, 'transport_outcome_unknown');
		$this->deliveries->expects($this->once())->method('finish')
			->with(31, NotificationDeliveryState::UNKNOWN);

		$this->expectException(\RuntimeException::class);
		$this->send('timeout', 'sign_request', $this->file, $this->request, 'signer@example.invalid',
			static function (): void {
				throw new \RuntimeException('timeout after SMTP DATA');
			},
		);
	}

	public function testProgrammingErrorIsNotSwallowedButAuditIsUnknown(): void {
		$this->expectFreshDelivery();
		$this->attempts->expects($this->once())->method('finish')
			->with(41, NotificationDeliveryState::UNKNOWN, 'unexpected_error');

		$this->expectException(\TypeError::class);
		$this->send('bug', 'sign_request', $this->file, $this->request, 'signer@example.invalid',
			static function (): void {
				throw new \TypeError('programming error');
			},
		);
	}

	public function testNeverCallsSmtpInsideAnOpenDomainTransaction(): void {
		$this->db->method('inTransaction')->willReturn(true);
		$this->deliveries->expects($this->never())->method('register');
		$this->expectException(\LogicException::class);
		$this->send('uncommitted', 'sign_request', $this->file, $this->request, 'signer@example.invalid',
			static function (): void {
				throw new \LogicException('This should not be reached');
			},
		);
	}
}
