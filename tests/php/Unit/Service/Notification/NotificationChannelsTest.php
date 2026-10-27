<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\MailService;
use OCA\Libresign\Service\Notification\GatewayChannel;
use OCA\Libresign\Service\Notification\GatewayPayload;
use OCA\Libresign\Service\Notification\InAppChannel;
use OCA\Libresign\Service\Notification\InAppPayload;
use OCA\Libresign\Service\Notification\MailChannel;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\TwofactorGatewayService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class NotificationChannelsTest extends TestCase {
	private function request(): SignRequest {
		$request = new SignRequest();
		$request->setId(42);
		$request->setUuid('synthetic-request');
		$request->setDescription('Review document');
		return $request;
	}

	private function file(): File {
		$file = new File();
		$file->setId(31);
		$file->setName('Synthetic.pdf');
		$file->setUuid('synthetic-file');
		$file->setUserId('synthetic-owner');
		return $file;
	}

	public function testMailChannelChoosesInitialTemplateOnlyForFirstMail(): void {
		$mapper = $this->createMock(SignRequestMapper::class);
		$mapper->method('incrementNotificationCounter')->with($this->isInstanceOf(SignRequest::class), 'mail')->willReturnOnConsecutiveCalls(true, false);
		$mail = $this->createMock(MailService::class);
		$mail->expects($this->once())->method('notifyUnsignedUser');
		$mail->expects($this->once())->method('notifySignDataUpdated');
		$channel = new MailChannel($mail, $mapper);
		$message = new NotificationMessage('business-event', 'sign_request', 'mail', $this->file(), $this->request(), 'synthetic@example.invalid', new MailPayload());
		$channel->deliver($message);
		$channel->deliver($message);
	}

	public function testGatewayChannelKeepsProviderAndRecipientSeparate(): void {
		$mapper = $this->createMock(SignRequestMapper::class);
		$mapper->expects($this->once())->method('incrementNotificationCounter')
			->with($this->isInstanceOf(SignRequest::class), 'sms')->willReturn(true);
		// Test the real final integration bridge with a synthetic provider.
		$provider = new class {
			/** @var array{string, string, string}|null */
			public ?array $sent = null;
			public function send(string $gateway, string $recipient, string $body): void {
				$this->sent = [$gateway, $recipient, $body];
			}
		};
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($provider);
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->willReturn(true);
		$gateway = new TwofactorGatewayService($container, $appManager, $this->createMock(LoggerInterface::class));
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $value): string => $value);
		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRouteAbsolute')->willReturn('https://example.invalid/sign');
		$channel = new GatewayChannel($gateway, $mapper, $l10n, $url);
		$this->assertTrue($channel->supports('gateway:sms'));
		$this->assertFalse($channel->supports('mail'));
		$channel->deliver(new NotificationMessage('business-event', 'sign_request', 'gateway:sms', $this->file(), $this->request(), '5550000000', new GatewayPayload('sms', 'sms')));
		$this->assertSame('sms', $provider->sent[0]);
		$this->assertSame('5550000000', $provider->sent[1]);
		$this->assertStringContainsString('Synthetic.pdf', $provider->sent[2]);
	}

	public function testInAppChannelUsesIndependentFirstNotificationState(): void {
		$mapper = $this->createMock(SignRequestMapper::class);
		$mapper->expects($this->once())->method('incrementNotificationCounter')
			->with($this->isInstanceOf(SignRequest::class), 'notify')->willReturn(true);
		$manager = $this->createMock(IManager::class);
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())->method('setSubject')
			->with('new_sign_request', ['file' => 'synthetic'])->willReturnSelf();
		$manager->expects($this->once())->method('notify')->with($notification);
		$channel = new InAppChannel($manager, $mapper);
		$channel->deliver(new NotificationMessage('business-event', 'sign_request', 'nextcloud', $this->file(), $this->request(), 'synthetic-owner', new InAppPayload($notification, ['file' => 'synthetic'])));
	}
}
