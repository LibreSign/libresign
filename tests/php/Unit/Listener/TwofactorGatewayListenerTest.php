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
use OCA\Libresign\Service\Notification\WhatsAppBusinessTemplateOptions;
use OCA\Libresign\Service\NotificationDeliveryService;
use OCA\Libresign\Service\TwofactorGatewayService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class TwofactorGatewayListenerTest extends TestCase {
	private function listener(NotificationDeliveryService $delivery, array $settings = []): TwofactorGatewayListener {
		$apps = $this->createMock(IAppManager::class);
		$apps->method('isEnabledForAnyone')->with('twofactor_gateway')->willReturn(true);
		$service = new TwofactorGatewayService(
			$this->createMock(ContainerInterface::class), $apps, $this->createMock(LoggerInterface::class),
		);
		$mapper = $this->createMock(SignRequestMapper::class);
		$mapper->method('getByIdUncached')->willReturn(new SignRequest());
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);
		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRouteAbsolute')->willReturn('https://example.invalid/sign');
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => $settings[$key] ?? $default,
		);
		$composer = new GatewayNotificationComposer(
			new NotificationHistoryService($mapper), $l10n, $url, new WhatsAppBusinessTemplateOptions($config),
		);
		return new TwofactorGatewayListener($service, $delivery, new GatewayChannel($service), $composer);
	}

	private function invitation(string $method, string $recipient): SendSignNotificationEvent {
		$entity = new IdentifyMethod();
		$entity->setIdentifierKey($method);
		$entity->setIdentifierValue($recipient);
		$identify = $this->createMock(IIdentifyMethod::class);
		$identify->method('getEntity')->willReturn($entity);
		$request = new SignRequest();
		$request->setId(42);
		$request->setUuid('synthetic-request');
		$file = new File();
		$file->setId(31);
		$file->setName('Example.pdf');
		$file->setUuid('synthetic-file');
		return new SendSignNotificationEvent($request, $file, $identify);
	}

	public function testStandardGatewayNotificationRetainsTextOnlyPayload(): void {
		$delivery = $this->createMock(NotificationDeliveryService::class);
		$delivery->expects($this->once())->method('dispatch')->willReturnCallback(
			function (NotificationMessage $message, NotificationChannel $channel): bool {
				$this->assertSame('gateway:sms', $message->channel);
				$this->assertSame('sign_request', $message->eventType);
				$this->assertSame('5550000000', $message->recipient);
				$this->assertSame([], $message->payload->extra);
				$this->assertTrue($channel->supports($message->channel));
				return true;
			},
		);
		$this->listener($delivery)->handle($this->invitation('sms', '5550000000'));
	}

	public function testBusinessNotificationReceivesOrderedTemplateOptions(): void {
		$delivery = $this->createMock(NotificationDeliveryService::class);
		$delivery->expects($this->once())->method('dispatch')->willReturnCallback(
			function (NotificationMessage $message): bool {
				$this->assertSame('gateway:whatsappbusiness', $message->channel);
				$this->assertSame('5511999990000', $message->recipient);
				$this->assertSame('UTILITY', $message->payload->extra['template_category']);
				$this->assertSame(2, $message->payload->extra['expected_body_parameter_count']);
				$this->assertStringContainsString('Example.pdf', $message->payload->extra['body_parameters'][0]);
				$this->assertSame('https://example.invalid/sign', $message->payload->extra['body_parameters'][1]);
				return true;
			},
		);
		$settings = [
			'whatsapp_business_template_name' => 'synthetic_utility',
			'whatsapp_business_template_language' => 'pt_BR',
		];
		$this->listener($delivery, $settings)->handle($this->invitation('whatsappbusiness', '5511999990000'));
	}

	public function testNotificationErrorsStillPropagate(): void {
		$delivery = $this->createMock(NotificationDeliveryService::class);
		$delivery->method('dispatch')->willThrowException(new \RuntimeException('Synthetic transport failure'));
		$this->expectException(\RuntimeException::class);
		$this->listener($delivery)->handle($this->invitation('sms', '5550000000'));
	}
}
