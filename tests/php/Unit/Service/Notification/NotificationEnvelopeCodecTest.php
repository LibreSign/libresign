<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\Notification\DataPayload;
use OCA\Libresign\Service\Notification\GatewayPayload;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationEnvelopeCodec;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCP\Notification\IManager;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;

final class NotificationEnvelopeCodecTest extends TestCase {
	private function codec(): NotificationEnvelopeCodec {
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(static fn (string $plain): string => 'sealed:' . base64_encode($plain));
		$crypto->method('decrypt')->willReturnCallback(static fn (string $sealed): string => base64_decode(substr($sealed, 7), true));
		return new NotificationEnvelopeCodec($crypto, $this->createMock(IManager::class));
	}

	private function message(): NotificationMessage {
		$file = new File();
		$file->setId(31);
		$file->setUuid('synthetic-file');
		$file->setName('Contract');
		$file->setUserId('owner');
		$file->setNodeId(99);

		$request = new SignRequest();
		$request->setId(42);
		$request->setFileId(31);
		$request->setUuid('synthetic-signer');
		$request->setDisplayName('Signer');
		$request->setDescription('Please sign');
		$request->setParticipantRole('signer');

		return new NotificationMessage(
			'sign-request-write:synthetic', 'sign_request', 'mail', $file, $request,
			'signer@example.invalid', new MailPayload(firstNotification: true), 'mail',
		);
	}

	public function testRestoresEncryptedMailSnapshotWithoutReadingDeletedSigners(): void {
		$message = $this->message();
		$codec = $this->codec();
		$encrypted = $codec->encode($message);
		$this->assertStringNotContainsString('signer@example.invalid', $encrypted);
		$this->assertStringNotContainsString('Please sign', $encrypted);

		$delivery = new NotificationDelivery();
		$delivery->setFileId(31);
		$delivery->setSignRequestId(42);
		$delivery->setEventType('sign_request');
		$delivery->setChannel('mail');
		$delivery->setRecipientEnc('sealed:' . base64_encode('signer@example.invalid'));
		$delivery->setMessageEnc($encrypted);
		$restored = $codec->decode($delivery);

		$this->assertSame('signer@example.invalid', $restored->recipient);
		$this->assertSame('Contract', $restored->file->getName());
		$this->assertSame('synthetic-signer', $restored->signRequest->getUuid());
		$this->assertSame('Please sign', $restored->signRequest->getDescription());
		$this->assertInstanceOf(MailPayload::class, $restored->payload);
		$this->assertTrue($restored->payload->firstNotification);
		$this->assertSame('mail', $restored->historyMethod);
	}

	public function testRejectsNotificationForAnotherDeliveryIdentity(): void {
		$message = $this->message();
		$delivery = new NotificationDelivery();
		$delivery->setFileId(99);
		$delivery->setSignRequestId(42);
		$delivery->setEventType('sign_request');
		$delivery->setChannel('mail');
		$delivery->setRecipientEnc('sealed:' . base64_encode('signer@example.invalid'));
		$delivery->setMessageEnc($this->codec()->encode($message));

		$this->expectException(\UnexpectedValueException::class);
		$this->codec()->decode($delivery);
	}

	public function testUnavailableSnapshotDoesNotTriggerTransport(): void {
		$delivery = new NotificationDelivery();
		$this->expectException(\UnexpectedValueException::class);
		$this->codec()->decode($delivery);
	}

	public function testExternalTransportPayloadNeedsNoCoreCodecChanges(): void {
		$codec = $this->codec();
		$original = $this->message();
		$message = new NotificationMessage(
			$original->sourceEventId,
			$original->eventType,
			'third-party:chat',
			$original->file,
			$original->signRequest,
			'synthetic-recipient',
			new DataPayload(['text' => 'Please sign', 'metadata' => ['source' => 'third-party', 'priority' => 1]]),
		);
		$delivery = new NotificationDelivery();
		$delivery->setFileId(31);
		$delivery->setSignRequestId(42);
		$delivery->setEventType('sign_request');
		$delivery->setChannel('third-party:chat');
		$delivery->setRecipientEnc('sealed:' . base64_encode('synthetic-recipient'));
		$delivery->setMessageEnc($codec->encode($message));

		$decoded = $codec->decode($delivery);
		$this->assertInstanceOf(DataPayload::class, $decoded->payload);
		$this->assertSame('synthetic-recipient', $decoded->recipient);
		$this->assertSame(['text' => 'Please sign', 'metadata' => ['source' => 'third-party', 'priority' => 1]], $decoded->payload->data);
	}

	public function testExtensionPayloadRejectsObjectsAndExcessiveNesting(): void {
		$this->expectException(\InvalidArgumentException::class);
		new DataPayload(['unsafe' => new \stdClass()]);
	}

	public function testExtensionPayloadRejectsExcessiveNesting(): void {
		$value = 'deep';
		for ($depth = 0; $depth < 9; $depth++) {
			$value = ['next' => $value];
		}
		$this->expectException(\InvalidArgumentException::class);
		new DataPayload(['root' => $value]);
	}

	public function testGatewayPayloadCanBeRestoredWithoutGatewayDependencies(): void {
		$message = $this->message();
		$gateway = new NotificationMessage(
			$message->sourceEventId, $message->eventType, 'gateway:sms',
			$message->file, $message->signRequest, '5550000', new GatewayPayload('sms', 'Please sign'),
		);
		$delivery = new NotificationDelivery();
		$delivery->setFileId(31);
		$delivery->setSignRequestId(42);
		$delivery->setEventType('sign_request');
		$delivery->setChannel('gateway:sms');
		$delivery->setRecipientEnc('sealed:' . base64_encode('5550000'));
		$delivery->setMessageEnc($this->codec()->encode($gateway));

		$restored = $this->codec()->decode($delivery);
		$this->assertInstanceOf(GatewayPayload::class, $restored->payload);
		$this->assertSame('sms', $restored->payload->gatewayName);
		$this->assertSame('Please sign', $restored->payload->body);
	}
}
