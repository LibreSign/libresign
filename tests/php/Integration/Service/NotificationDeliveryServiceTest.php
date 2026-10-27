<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service;

use OCA\Libresign\BackgroundJob\DeliverNotification;
use OCA\Libresign\Db\File;
use OCA\Libresign\Db\NotificationAttemptMapper;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Exception\MailTransportException;
use OCA\Libresign\Service\Notification\GatewayPayload;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationChannel;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\NotificationDeliveryService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\Server;

/** @group DB */
final class NotificationDeliveryServiceTest extends \OCA\Libresign\Tests\Integration\TestCase {
	private NotificationDeliveryMapper $deliveries;
	private NotificationAttemptMapper $attempts;
	private NotificationDeliveryService $service;
	/** @var list<string> */
	private array $keys = [];

	public function setUp(): void {
		parent::setUp();
		$this->deliveries = Server::get(NotificationDeliveryMapper::class);
		$this->attempts = Server::get(NotificationAttemptMapper::class);
		$this->service = Server::get(NotificationDeliveryService::class);
	}

	public function tearDown(): void {
		foreach ($this->keys as $key) {
			$this->deliveries->delete($this->deliveries->findByEventKey($key));
		}
		$this->keys = [];
		parent::tearDown();
	}

	private function file(): File {
		$file = new File();
		$file->setId(101);
		$file->setUserId('synthetic-owner');
		return $file;
	}

	private function request(): SignRequest {
		$request = new SignRequest();
		$request->setId(201);
		return $request;
	}

	private function recordKey(int $fileId, int $requestId, string $source, string $channel = 'mail', string $recipient = 'synthetic@example.invalid'): string {
		$secret = Server::get(\OCP\IConfig::class)->getSystemValueString('secret');
		return hash_hmac('sha256',
			$source . "\0sign_request\0" . $channel . "\0" . $fileId . "\0" . $requestId . "\0" . $recipient,
			hash_hmac('sha256', 'libresign-notification-v2', $secret, true),
		);
	}

	private function send(
		string $source,
		string $eventType,
		File $file,
		SignRequest $request,
		string $recipient,
		callable $callback,
	): bool {
		$channel = new class(\Closure::fromCallable($callback)) implements NotificationChannel {
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
			$source, $eventType, 'mail', $file, $request, $recipient, new MailPayload(),
		), $channel);
	}

	public function testRolledBackDomainIntentDoesNotSurviveOrReachTransport(): void {
		$source = 'rollback-outbox-' . bin2hex(random_bytes(8));
		$key = $this->recordKey(101, 201, $source);
		$db = Server::get(IDBConnection::class);
		$jobs = Server::get(IJobList::class);
		$calls = 0;
		$deliveryId = null;
		$db->beginTransaction();
		try {
			$this->assertTrue($this->send($source, 'sign_request', $this->file(), $this->request(),
				'synthetic@example.invalid', static function () use (&$calls): void {
					$calls++;
				},
			));
			$delivery = $this->deliveries->findByEventKey($key);
			$deliveryId = $delivery->getId();
			$this->assertSame(NotificationDeliveryState::PENDING->value, $delivery->getState());
			$this->assertSame(0, $calls);
			$this->assertTrue($jobs->has(DeliverNotification::class, ['id' => $deliveryId]));
		} finally {
			$db->rollBack();
		}

		$this->service->flushCommitted();
		$this->assertSame(0, $calls);
		if ($deliveryId !== null) {
			$this->assertFalse($jobs->has(DeliverNotification::class, ['id' => $deliveryId]));
		}
		$this->expectException(DoesNotExistException::class);
		$this->deliveries->findByEventKey($key);
	}

	public function testCommittedIntentSubmitsOnlyAfterDomainCommit(): void {
		$source = 'commit-outbox-' . bin2hex(random_bytes(8));
		$key = $this->recordKey(101, 201, $source);
		$db = Server::get(IDBConnection::class);
		$jobs = Server::get(IJobList::class);
		$calls = 0;
		$deliveryId = null;
		$db->beginTransaction();
		try {
			$this->assertTrue($this->send($source, 'sign_request', $this->file(), $this->request(),
				'synthetic@example.invalid', static function () use (&$calls): void {
					$calls++;
				},
			));
			$deliveryId = $this->deliveries->findByEventKey($key)->getId();
			$this->assertSame(0, $calls);
			$db->commit();
		} finally {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
		}
		$this->keys[] = $key;
		try {
			$this->service->flushCommitted();
			$this->assertSame(1, $calls);
			$this->assertSame(NotificationDeliveryState::ACCEPTED->value,
				$this->deliveries->findByEventKey($key)->getState());
		} finally {
			if ($deliveryId !== null) {
				$jobs->remove(DeliverNotification::class, ['id' => $deliveryId]);
			}
		}
	}

	public function testRealDatabaseMakesOneSmtpSubmissionAndRecordsAcceptance(): void {
		$source = 'integration-' . bin2hex(random_bytes(8));
		$key = $this->recordKey(101, 201, $source);
		$this->keys[] = $key;
		$counter = 0;
		$send = static function () use (&$counter): void {
			$counter++;
		};
		$this->assertTrue($this->send($source, 'sign_request', $this->file(), $this->request(), 'synthetic@example.invalid', $send));
		$this->assertFalse($this->send($source, 'sign_request', $this->file(), $this->request(), 'synthetic@example.invalid', $send));
		$this->assertSame(1, $counter);
		$persisted = $this->deliveries->findByEventKey($key);
		$this->assertSame(NotificationDeliveryState::ACCEPTED->value, $persisted->getState());
		$this->assertNotSame('synthetic@example.invalid', $persisted->getRecipientEnc());
		$attempts = $this->attempts->findForDelivery($persisted->getId());
		$this->assertCount(1, $attempts);
		$this->assertSame(NotificationDeliveryState::ACCEPTED->value, $attempts[0]->getState());
	}

	public function testAmbiguousSubmissionNeverRetriesEvenWhenEventReplayed(): void {
		$source = 'integration-unknown-' . bin2hex(random_bytes(8));
		$key = $this->recordKey(101, 201, $source);
		$this->keys[] = $key;
		try {
			$this->send($source, 'sign_request', $this->file(), $this->request(), 'synthetic@example.invalid',
				static function (): void {
					throw new MailTransportException('Synthetic timeout after DATA', NotificationDeliveryState::UNKNOWN, 'smtp_outcome_unknown');
				},
			);
			$this->fail('Expected mail transport failure');
		} catch (MailTransportException) {
			// The original transport exception is propagated to its caller.
		}

		$this->assertFalse($this->send($source, 'sign_request', $this->file(), $this->request(), 'synthetic@example.invalid',
			static function (): void {
				throw new \LogicException('An ambiguous send must not retry');
			},
		));
		$persisted = $this->deliveries->findByEventKey($key);
		$this->assertSame(NotificationDeliveryState::UNKNOWN->value, $persisted->getState());
		$this->assertSame('smtp_outcome_unknown', $this->attempts->findForDelivery($persisted->getId())[0]->getReasonCode());
	}
	public function testFailedMailDoesNotBlockOtherChannelsForSameBusinessEvent(): void {
		$source = 'integration-multichannel-' . bin2hex(random_bytes(9));
		$mailKey = $this->recordKey(101, 201, $source);
		$gatewayKey = $this->recordKey(101, 201, $source, 'gateway:sms', '5550000000');
		array_push($this->keys, $mailKey, $gatewayKey);
		try {
			$this->send($source, 'sign_request', $this->file(), $this->request(), 'synthetic@example.invalid',
				static function (): void {
					throw new MailTransportException('SMTP response was lost', NotificationDeliveryState::UNKNOWN, 'smtp_outcome_unknown');
				},
			);
			$this->fail('Expected SMTP ambiguity');
		} catch (MailTransportException) {
			// Verify that an independent channel remains available.
		}
		$gatewayCalls = 0;
		$gateway = new class(\Closure::fromCallable(static function () use (&$gatewayCalls): void {
			$gatewayCalls++;
		})) implements NotificationChannel {
			public function __construct(
				private \Closure $send,
			) {
			}
			public function supports(string $channel): bool {
				return $channel === 'gateway:sms';
			}
			public function deliver(NotificationMessage $message): void {
				($this->send)();
			}
		};
		$message = new NotificationMessage(
			$source, 'sign_request', 'gateway:sms', $this->file(), $this->request(),
			'5550000000', new GatewayPayload('sms', 'sms'),
		);
		$this->assertTrue($this->service->dispatch($message, $gateway));
		$this->assertFalse($this->service->dispatch($message, $gateway));
		$this->assertSame(1, $gatewayCalls);
		$this->assertSame(NotificationDeliveryState::UNKNOWN->value, $this->deliveries->findByEventKey($mailKey)->getState());
		$this->assertSame(NotificationDeliveryState::ACCEPTED->value, $this->deliveries->findByEventKey($gatewayKey)->getState());
		$this->assertSame(
			$this->deliveries->findByEventKey($mailKey)->getCorrelationId(),
			$this->deliveries->findByEventKey($gatewayKey)->getCorrelationId(),
		);
		$this->assertCount(2, $this->deliveries->findForEvent($this->deliveries->findByEventKey($mailKey)->getCorrelationId()));
	}

}
