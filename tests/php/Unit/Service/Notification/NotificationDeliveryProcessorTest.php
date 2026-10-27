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
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Enum\NotificationDeliveryState;
use OCA\Libresign\Enum\SignRequestStatus;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationChannel;
use OCA\Libresign\Service\Notification\NotificationDeliveryProcessor;
use OCA\Libresign\Service\Notification\NotificationEnvelopeCodec;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\Notification\NotificationModule;
use OCA\Libresign\Service\Notification\NotificationModuleCatalog;
use OCA\Libresign\Service\Notification\NotificationSubmissionService;
use OCA\Libresign\Service\Notification\NotificationTransportRegistry;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Notification\IManager;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NotificationDeliveryProcessorTest extends TestCase {
	private function delivery(): NotificationDelivery {
		$delivery = new NotificationDelivery();
		$delivery->setId(31);
		$delivery->setFileId(12);
		$delivery->setSignRequestId(23);
		$delivery->setState(NotificationDeliveryState::PENDING->value);
		$delivery->setEventType('sign_request');
		$delivery->setChannel('mail');
		$delivery->setRecipientEnc('sealed:signer@example.invalid');
		return $delivery;
	}

	private function codec(): NotificationEnvelopeCodec {
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(static fn (string $s): string => 'sealed:' . $s);
		$crypto->method('decrypt')->willReturnCallback(static fn (string $s): string => substr($s, 7));
		return new NotificationEnvelopeCodec($crypto, $this->createMock(IManager::class));
	}

	private function registry(): NotificationTransportRegistry {
		$channel = $this->createMock(NotificationChannel::class);
		$channel->method('supports')->willReturnCallback(static fn (string $name): bool => $name === 'mail');
		$module = $this->createMock(NotificationModule::class);
		$module->method('transport')->willReturn($channel);
		return new NotificationTransportRegistry(new NotificationModuleCatalog([$module]));
	}

	private function enqueueSnapshot(NotificationDelivery $delivery, NotificationEnvelopeCodec $codec): void {
		$file = new File();
		$file->setId(12);
		$file->setUuid('file-uuid');
		$file->setUserId('owner');
		$file->setName('Contract');
		$signRequest = new SignRequest();
		$signRequest->setId(23);
		$signRequest->setFileId(12);
		$signRequest->setUuid('request-uuid');
		$delivery->setMessageEnc($codec->encode(new NotificationMessage(
			'write:one', $delivery->getEventType(), 'mail', $file, $signRequest,
			'signer@example.invalid', new MailPayload(firstNotification: true),
		)));
	}

	public function testPendingHandoffCanBeProcessedWithoutOriginalRequest(): void {
		$codec = $this->codec();
		$delivery = $this->delivery();
		$delivery->setEventType('sign_request_canceled');
		$this->enqueueSnapshot($delivery, $codec);
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->method('findById')->with(31)->willReturn($delivery);
		$submission = $this->createMock(NotificationSubmissionService::class);
		$submission->expects($this->once())->method('submit')
			->with($delivery, $this->callback(static fn (NotificationMessage $msg): bool => $msg->recipient === 'signer@example.invalid'),
				$this->isInstanceOf(NotificationChannel::class))
			->willReturn(true);
		(new NotificationDeliveryProcessor(
			$mapper, $this->createMock(SignRequestMapper::class), $codec, $this->registry(), $submission, $this->createMock(LoggerInterface::class),
		))->process(31);
	}

	public function testPendingInviteForRemovedSignerIsCanceledBeforeTransport(): void {
		$delivery = $this->delivery();
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->method('findById')->willReturn($delivery);
		$mapper->expects($this->once())->method('cancelPendingInvitations')->with(23)->willReturn(1);
		$requests = $this->createMock(SignRequestMapper::class);
		$requests->method('getByIdUncached')->willThrowException(new DoesNotExistException('Removed signer'));
		$submission = $this->createMock(NotificationSubmissionService::class);
		$submission->expects($this->never())->method('submit');
		(new NotificationDeliveryProcessor(
			$mapper, $requests, $this->codec(), $this->registry(), $submission, $this->createMock(LoggerInterface::class),
		))->process(31);
	}

	public function testPendingInviteForSignedSignerIsCanceledBeforeTransport(): void {
		$delivery = $this->delivery();
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->method('findById')->willReturn($delivery);
		$mapper->expects($this->once())->method('cancelPendingInvitations')->with(23)->willReturn(1);
		$signRequest = new SignRequest();
		$signRequest->setStatusEnum(SignRequestStatus::SIGNED);
		$requests = $this->createMock(SignRequestMapper::class);
		$requests->method('getByIdUncached')->willReturn($signRequest);
		$submission = $this->createMock(NotificationSubmissionService::class);
		$submission->expects($this->never())->method('submit');
		(new NotificationDeliveryProcessor(
			$mapper, $requests, $this->codec(), $this->registry(), $submission, $this->createMock(LoggerInterface::class),
		))->process(31);
	}

	public function testPendingInviteForActiveSignerCanSubmit(): void {
		$codec = $this->codec();
		$delivery = $this->delivery();
		$this->enqueueSnapshot($delivery, $codec);
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->method('findById')->willReturn($delivery);
		$mapper->expects($this->never())->method('cancelPendingInvitations');
		$signRequest = new SignRequest();
		$signRequest->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
		$requests = $this->createMock(SignRequestMapper::class);
		$requests->method('getByIdUncached')->willReturn($signRequest);
		$submission = $this->createMock(NotificationSubmissionService::class);
		$submission->expects($this->once())->method('submit')->willReturn(true);
		(new NotificationDeliveryProcessor(
			$mapper, $requests, $codec, $this->registry(), $submission, $this->createMock(LoggerInterface::class),
		))->process(31);
	}

	public function testCompletedDeliveryIsNeverSubmittedAgain(): void {
		$delivery = $this->delivery();
		$delivery->setState(NotificationDeliveryState::UNKNOWN->value);
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->method('findById')->willReturn($delivery);
		$submission = $this->createMock(NotificationSubmissionService::class);
		$submission->expects($this->never())->method('submit');
		(new NotificationDeliveryProcessor(
			$mapper, $this->createMock(SignRequestMapper::class), $this->codec(), $this->registry(), $submission, $this->createMock(LoggerInterface::class),
		))->process(31);
	}

	public function testMalformedHandoffIsMarkedFailedBeforeAnyTransport(): void {
		$delivery = $this->delivery();
		$mapper = $this->createMock(NotificationDeliveryMapper::class);
		$mapper->method('findById')->willReturn($delivery);
		$mapper->expects($this->once())->method('failPending')->with(31)->willReturn(true);
		$requests = $this->createMock(SignRequestMapper::class);
		$valid = new SignRequest();
		$valid->setStatusEnum(SignRequestStatus::ABLE_TO_SIGN);
		$requests->method('getByIdUncached')->willReturn($valid);
		$submission = $this->createMock(NotificationSubmissionService::class);
		$submission->expects($this->never())->method('submit');
		(new NotificationDeliveryProcessor(
			$mapper, $requests, $this->codec(), $this->registry(), $submission, $this->createMock(LoggerInterface::class),
		))->process(31);
	}

	public function testNewModuleCanSupplyTransportWithoutChangingDispatcher(): void {
		$registry = $this->registry();
		$this->assertTrue($registry->resolve('mail')->supports('mail'));
		$this->expectException(\LogicException::class);
		$registry->resolve('gateway:unknown');
	}
}
