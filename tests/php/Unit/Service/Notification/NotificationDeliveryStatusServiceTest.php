<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Db\NotificationDelivery;
use OCA\Libresign\Db\NotificationDeliveryMapper;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\Notification\NotificationDeliveryKeyFactory;
use OCA\Libresign\Service\Notification\NotificationDeliveryStatusService;
use OCA\Libresign\Service\Validation\FileInputValidator;
use OCA\Libresign\Service\Validation\SigningRequestValidator;
use OCP\IConfig;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class NotificationDeliveryStatusServiceTest extends TestCase {
	private SigningRequestValidator $validator;
	private SignRequestMapper $requests;
	private NotificationDeliveryMapper $deliveries;
	private NotificationDeliveryStatusService $service;

	protected function setUp(): void {
		$this->validator = $this->createMock(SigningRequestValidator::class);
		$this->requests = $this->createMock(SignRequestMapper::class);
		$this->deliveries = $this->createMock(NotificationDeliveryMapper::class);
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')->with('secret')->willReturn('synthetic-secret');
		$this->service = new NotificationDeliveryStatusService(
			$this->validator, $this->createMock(FileInputValidator::class),
			$this->createMock(IUserSession::class), $this->requests, $this->deliveries,
			new NotificationDeliveryKeyFactory($config),
		);
	}

	public function testAuthorizationPrecedesLookupOfSignerOrDeliveries(): void {
		$this->validator->method('iRequestedSignThisFile')
			->willThrowException(new LibresignException('Not authorized'));
		$this->requests->expects($this->never())->method('getByFileIdAndSignRequestId');
		$this->deliveries->expects($this->never())->method('findForEvent');
		$this->expectException(LibresignException::class);
		$this->service->status(12, 23, str_repeat('a', 32));
	}

	public function testStateIsPerChannelAndDoesNotReturnRecipientData(): void {
		$this->requests->method('getByFileIdAndSignRequestId')->willReturn(new SignRequest());
		$pending = new NotificationDelivery();
		$pending->setFileId(12);
		$pending->setSignRequestId(23);
		$pending->setEventType('sign_request');
		$pending->setChannel('mail');
		$pending->setState('sending');
		$pending->setRecipientEnc('secret-recipient');
		$accepted = new NotificationDelivery();
		$accepted->setFileId(12);
		$accepted->setSignRequestId(23);
		$accepted->setEventType('sign_request');
		$accepted->setChannel('nextcloud');
		$accepted->setState('accepted');
		$this->deliveries->method('findForEvent')->willReturn([$pending, $accepted]);
		$result = $this->service->status(12, 23, str_repeat('a', 32));
		$this->assertSame('processing', $result['status']);
		$this->assertFalse($result['complete']);
		$this->assertCount(2, $result['channels']);
		$this->assertStringNotContainsString('secret-recipient', json_encode($result));
	}

	public function testUnknownResultIsTerminalButRequiresAttention(): void {
		$this->requests->method('getByFileIdAndSignRequestId')->willReturn(new SignRequest());
		$unknown = new NotificationDelivery();
		$unknown->setFileId(12);
		$unknown->setSignRequestId(23);
		$unknown->setEventType('sign_request');
		$unknown->setChannel('mail');
		$unknown->setState('unknown');
		$this->deliveries->method('findForEvent')->willReturn([$unknown]);
		$status = $this->service->status(12, 23, str_repeat('b', 32));
		$this->assertTrue($status['complete']);
		$this->assertSame('attention_required', $status['status']);
	}

	public function testInvalidRequestIdCannotReadDeliveryRecords(): void {
		$this->requests->method('getByFileIdAndSignRequestId')->willReturn(new SignRequest());
		$this->deliveries->expects($this->never())->method('findForEvent');
		$this->expectException(LibresignException::class);
		$this->service->status(12, 23, 'short');
	}
}
