<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationChannel;
use OCA\Libresign\Service\Notification\NotificationDeliveryKeyFactory;
use OCA\Libresign\Service\Notification\NotificationDeliveryRegistrar;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;

final class NotificationDeliveryRegistrarTest extends TestCase {
	private NotificationDeliveryMapper $deliveries;
	private ICrypto $crypto;
	private IDBConnection $db;
	private NotificationDeliveryRegistrar $registrar;
	private NotificationChannel $channel;

	protected function setUp(): void {
		$this->deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->db = $this->createMock(IDBConnection::class);
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')->with('secret')->willReturn('synthetic-secret');
		$this->channel = $this->createMock(NotificationChannel::class);
		$this->channel->method('supports')->willReturnCallback(static fn (string $channel): bool => $channel === 'mail');
		$this->registrar = new NotificationDeliveryRegistrar(
			$this->deliveries, new NotificationDeliveryKeyFactory($config),
			$this->crypto, $this->db,
		);
	}

	private function message(string $recipient = 'signer@example.invalid', string $channel = 'mail'): NotificationMessage {
		$file = new File();
		$file->setId(40);
		$file->setUserId('synthetic-owner');
		$signRequest = new SignRequest();
		$signRequest->setId(25);
		return new NotificationMessage(
			'event-1', 'sign_request', $channel, $file, $signRequest,
			$recipient, new MailPayload(),
		);
	}

	public function testRegistrationEncryptsRecipientAndUsesStableCorrelationKeys(): void {
		$this->crypto->expects($this->once())->method('encrypt')
			->with('signer@example.invalid')->willReturn('encrypted-value');
		$delivery = new NotificationDelivery();
		$delivery->setId(31);
		$this->deliveries->expects($this->once())->method('register')
			->with(
				$this->callback(static fn (string $value): bool => strlen($value) === 64 && ctype_xdigit($value)),
				$this->callback(static fn (string $value): bool => strlen($value) === 64 && ctype_xdigit($value)),
				'sign_request', 'mail', 40, 25, 'synthetic-owner', 'encrypted-value', null,
			)->willReturn($delivery);

		$this->assertSame($delivery, $this->registrar->register($this->message(), $this->channel));
	}

	public function testRegistrationMayJoinTheOpenDomainTransaction(): void {
		$this->db->method('inTransaction')->willReturn(true);
		$this->crypto->method('encrypt')->willReturn('encrypted');
		$delivery = new NotificationDelivery();
		$this->deliveries->expects($this->once())->method('register')->willReturn($delivery);
		$this->assertSame($delivery, $this->registrar->register($this->message(), $this->channel, 'encrypted-handoff'));
	}

	public function testUnsupportedChannelIsRejectedBeforePersisting(): void {
		$this->crypto->expects($this->never())->method('encrypt');
		$this->deliveries->expects($this->never())->method('register');
		$this->expectException(\InvalidArgumentException::class);
		$this->registrar->register($this->message('signer@example.invalid', 'new-unknown-channel'), $this->channel);
	}

	public function testBlankRecipientIsRejectedBeforeEncrypting(): void {
		$this->crypto->expects($this->never())->method('encrypt');
		$this->deliveries->expects($this->never())->method('register');
		$this->expectException(\InvalidArgumentException::class);
		$this->registrar->register($this->message(' '), $this->channel);
	}
}
