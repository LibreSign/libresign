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
use OCA\Libresign\Service\Notification\GatewayNotificationComposer;
use OCA\Libresign\Service\Notification\GatewayPayload;
use OCA\Libresign\Service\Notification\InAppChannel;
use OCA\Libresign\Service\Notification\InAppPayload;
use OCA\Libresign\Service\Notification\MailChannel;
use OCA\Libresign\Service\Notification\MailPayload;
use OCA\Libresign\Service\Notification\NotificationHistoryService;
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

	public function testMailChannelUsesPreparedInitialTemplateWithoutChangingHistory(): void {
		$mail = $this->createMock(MailService::class);
		$mail->expects($this->once())->method('notifyUnsignedUser');
		$mail->expects($this->once())->method('notifySignDataUpdated');
		$channel = new MailChannel($mail);
		$channel->deliver(new NotificationMessage(
			'business-event', 'sign_request', 'mail', $this->file(), $this->request(),
			'synthetic@example.invalid', new MailPayload(firstNotification: true),
		));
		$channel->deliver(new NotificationMessage(
			'business-event-next', 'sign_request', 'mail', $this->file(), $this->request(),
			'synthetic@example.invalid', new MailPayload(firstNotification: false),
		));
	}

	public function testGatewayChannelKeepsProviderAndRecipientSeparate(): void {
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
		$channel = new GatewayChannel($gateway);
		$mapper = $this->createMock(SignRequestMapper::class);
		$mapper->method('getByIdUncached')->willReturn(new SignRequest());
		$composer = new GatewayNotificationComposer(new NotificationHistoryService($mapper), $l10n, $url);
		$body = $composer->compose('sign_request', $this->request(), $this->file(), 'sms');
		$this->assertTrue($channel->supports('gateway:sms'));
		$this->assertFalse($channel->supports('mail'));
		$channel->deliver(new NotificationMessage('business-event', 'sign_request', 'gateway:sms', $this->file(), $this->request(), '5550000000', new GatewayPayload('sms', $body)));
		$this->assertSame('sms', $provider->sent[0]);
		$this->assertSame('5550000000', $provider->sent[1]);
		$this->assertStringContainsString('Synthetic.pdf', $provider->sent[2]);
	}

	public function testInAppChannelOnlySendsAnAlreadyPreparedNotification(): void {
		$manager = $this->createMock(IManager::class);
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->never())->method('setSubject');
		$manager->expects($this->once())->method('notify')->with($notification);
		$channel = new InAppChannel($manager);
		$channel->deliver(new NotificationMessage(
			'business-event', 'sign_request', 'nextcloud', $this->file(), $this->request(),
			'synthetic-owner', new InAppPayload($notification, ['file' => 'synthetic']),
		));
	}
}
