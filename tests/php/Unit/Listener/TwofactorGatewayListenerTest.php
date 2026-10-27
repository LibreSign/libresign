<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Listener;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\IdentifyMethod;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Events\SendSignNotificationEvent;
use OCA\Libresign\Listener\TwofactorGatewayListener;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\Notification\GatewayChannel;
use OCA\Libresign\Service\Notification\GatewayNotificationComposer;
use OCA\Libresign\Service\Notification\NotificationChannel;
use OCA\Libresign\Service\Notification\NotificationHistoryService;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\NotificationDeliveryService;
use OCA\Libresign\Service\TwofactorGatewayService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class TwofactorGatewayListenerTest extends TestCase {
	public function testGatewayInvitationUsesChannelIdentityAndPreservesEventId(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->with('twofactor_gateway')->willReturn(true);
		$gatewayService = new TwofactorGatewayService(
			$this->createMock(ContainerInterface::class), $appManager, $this->createMock(LoggerInterface::class),
		);
		$delivery = $this->createMock(NotificationDeliveryService::class);
		$delivery->expects($this->once())->method('dispatch')->willReturnCallback(
			function (NotificationMessage $message, NotificationChannel $channel): bool {
				$this->assertSame('gateway:sms', $message->channel);
				$this->assertSame('sign_request', $message->eventType);
				$this->assertSame('5550000000', $message->recipient);
				$this->assertTrue($channel->supports($message->channel));
				$this->assertNotSame('', $message->sourceEventId);
				return true;
			},
		);
		$mapper = $this->createMock(SignRequestMapper::class);
		$mapper->method('getByIdUncached')->willReturn(new SignRequest());
		$listener = new TwofactorGatewayListener(
			$gatewayService, $delivery, new GatewayChannel($gatewayService),
			new GatewayNotificationComposer(
				new NotificationHistoryService($mapper),
				$this->createMock(IL10N::class), $this->createMock(IURLGenerator::class),
			),
		);
		$entity = new IdentifyMethod();
		$entity->setIdentifierKey('sms');
		$entity->setIdentifierValue('5550000000');
		$method = $this->createMock(IIdentifyMethod::class);
		$method->method('getEntity')->willReturn($entity);
		$request = new SignRequest();
		$request->setId(44);
		$request->setUuid('synthetic-request');
		$file = new File();
		$file->setId(33);
		$file->setUuid('synthetic-file');
		$listener->handle(new SendSignNotificationEvent($request, $file, $method));
	}

	public function testGatewayFailureReachesTheChannelRouter(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->willReturn(true);
		$gatewayService = new TwofactorGatewayService(
			$this->createMock(ContainerInterface::class), $appManager, $this->createMock(LoggerInterface::class),
		);
		$delivery = $this->createMock(NotificationDeliveryService::class);
		$delivery->method('dispatch')->willThrowException(new \RuntimeException('secret gateway failure'));
		$mapper = $this->createMock(SignRequestMapper::class);
		$mapper->method('getByIdUncached')->willReturn(new SignRequest());
		$listener = new TwofactorGatewayListener(
			$gatewayService, $delivery, new GatewayChannel($gatewayService),
			new GatewayNotificationComposer(
				new NotificationHistoryService($mapper),
				$this->createMock(IL10N::class), $this->createMock(IURLGenerator::class),
			),
		);
		$entity = new IdentifyMethod();
		$entity->setIdentifierKey('sms');
		$entity->setIdentifierValue('5550000000');
		$method = $this->createMock(IIdentifyMethod::class);
		$method->method('getEntity')->willReturn($entity);
		$request = new SignRequest();
		$request->setId(44);
		$request->setUuid('synthetic-request');
		$file = new File();
		$file->setId(33);
		$file->setUuid('synthetic-file');

		$this->expectException(\RuntimeException::class);
		$listener->handle(new SendSignNotificationEvent($request, $file, $method, 'manual:test-event'));
	}
}
