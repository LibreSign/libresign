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

	public function testBusinessInvitationIncludesOrderedTemplateOptions(): void {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->willReturn(true);
		$service = new TwofactorGatewayService(
			$this->createMock(ContainerInterface::class), $appManager, $this->createMock(LoggerInterface::class),
		);
		$delivery = $this->createMock(NotificationDeliveryService::class);
		$delivery->expects($this->once())->method('dispatch')->willReturnCallback(
			function (NotificationMessage $message, NotificationChannel $channel): bool {
				$this->assertSame('gateway:whatsappbusiness', $message->channel);
				$this->assertSame('sign_request', $message->eventType);
				$this->assertSame('5511999990000', $message->recipient);
				$this->assertSame(2, $message->payload->extra['expected_body_parameter_count']);
				$this->assertCount(2, $message->payload->extra['body_parameters']);
				$this->assertStringContainsString('Synthetic.pdf', $message->payload->extra['body_parameters'][0]);
				$this->assertSame('https://example.invalid/sign', $message->payload->extra['body_parameters'][1]);
				return true;
			},
		);
		$mapper = $this->createMock(SignRequestMapper::class);
		$mapper->method('getByIdUncached')->willReturn(new SignRequest());
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $message): string => $message);
		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRouteAbsolute')->willReturn('https://example.invalid/sign');
		$listener = new TwofactorGatewayListener(
			$service, $delivery, new GatewayChannel($service),
			new GatewayNotificationComposer(new NotificationHistoryService($mapper), $l10n, $url),
		);
		$entity = new IdentifyMethod();
		$entity->setIdentifierKey('whatsappbusiness');
		$entity->setIdentifierValue('5511999990000');
		$method = $this->createMock(IIdentifyMethod::class);
		$method->method('getEntity')->willReturn($entity);
		$request = new SignRequest();
		$request->setId(42);
		$request->setUuid('synthetic-request');
		$file = new File();
		$file->setId(31);
		$file->setName('Synthetic.pdf');
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
