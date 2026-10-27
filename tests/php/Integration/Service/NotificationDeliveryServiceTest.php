<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\NotificationAttemptMapper;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Exception\MailTransportException;
use OCA\Libresign\Service\NotificationDeliveryService;
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

	private function recordKey(int $fileId, int $requestId, string $source): string {
		$secret = Server::get(\OCP\IConfig::class)->getSystemValueString('secret');
		return hash_hmac('sha256',
			$source . "\0sign_request\0" . $fileId . "\0" . $requestId . "\0synthetic@example.invalid",
			hash_hmac('sha256', 'libresign-notification-v1', $secret, true),
		);
	}

	public function testRealDatabaseMakesOneSmtpSubmissionAndRecordsAcceptance(): void {
		$source = 'integration-' . bin2hex(random_bytes(8));
		$key = $this->recordKey(101, 201, $source);
		$this->keys[] = $key;
		$counter = 0;
		$send = static function () use (&$counter): void {
			$counter++;
		};
		$this->assertTrue($this->service->send($source, 'sign_request', $this->file(), $this->request(), 'synthetic@example.invalid', $send));
		$this->assertFalse($this->service->send($source, 'sign_request', $this->file(), $this->request(), 'synthetic@example.invalid', $send));
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
			$this->service->send($source, 'sign_request', $this->file(), $this->request(), 'synthetic@example.invalid',
				static function (): void {
					throw new MailTransportException('Synthetic timeout after DATA', NotificationDeliveryState::UNKNOWN, 'smtp_outcome_unknown');
				},
			);
			$this->fail('Expected mail transport failure');
		} catch (MailTransportException) {
			// The original transport exception is propagated to its caller.
		}

		$this->assertFalse($this->service->send($source, 'sign_request', $this->file(), $this->request(), 'synthetic@example.invalid',
			static function (): void {
				throw new \LogicException('An ambiguous send must not retry');
			},
		));
		$persisted = $this->deliveries->findByEventKey($key);
		$this->assertSame(NotificationDeliveryState::UNKNOWN->value, $persisted->getState());
		$this->assertSame('smtp_outcome_unknown', $this->attempts->findForDelivery($persisted->getId())[0]->getReasonCode());
	}
}
