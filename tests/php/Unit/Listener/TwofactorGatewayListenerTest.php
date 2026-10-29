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
use OCA\Libresign\Events\SignedEvent;
use OCA\Libresign\Listener\TwofactorGatewayListener;
use OCA\Libresign\Service\IdentifyMethod\IdentifyService;
use OCA\Libresign\Service\IdentifyMethod\IIdentifyMethod;
use OCA\Libresign\Service\Notification\GatewayNotificationComposer;
use OCA\Libresign\Service\Notification\WhatsAppBusinessTemplateOptions;
use OCA\Libresign\Service\TwofactorGatewayService;
use OCP\App\IAppManager;
use OCP\Files\File as NextcloudFile;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class TwofactorGatewayListenerTest extends TestCase {
	/** @return array{TwofactorGatewayListener, GatewayDeliveryRecorder, SignRequestMapper, LoggerInterface} */
	private function listener(bool $configured, bool $enabled, bool $first): array {
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForAnyone')->willReturn($enabled);
		$transport = new GatewayDeliveryRecorder();
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($transport);
		$logger = $this->createMock(LoggerInterface::class);
		$mapper = $this->createMock(SignRequestMapper::class);
		$mapper->method('incrementNotificationCounter')->willReturn($first);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRouteAbsolute')->willReturnCallback(
			static fn (string $route): string => 'https://example.invalid/' . $route,
		);
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => ($configured ? [
				'whatsapp_business_template_name' => 'synthetic_utility',
				'whatsapp_business_template_language' => 'pt_BR',
			] : [])[$key] ?? $default,
		);
		$composer = new GatewayNotificationComposer($l10n, $url, new WhatsAppBusinessTemplateOptions($config));
		$listener = new TwofactorGatewayListener(
			$this->createMock(IUserSession::class),
			$this->createMock(IUserManager::class),
			$this->createMock(IdentifyService::class),
			$mapper,
			new TwofactorGatewayService($container, $appManager, $logger),
			$logger,
			$composer,
		);
		return [$listener, $transport, $mapper, $logger];
	}

	/** @return array{SignRequest, File, IIdentifyMethod} */
	private function invitation(string $method): array {
		$request = new SignRequest();
		$request->setId(42);
		$request->setUuid('synthetic-request');
		$request->setDescription('Synthetic description');
		$request->setDisplayName('Synthetic signer');
		$file = new File();
		$file->setName('Example.pdf');
		$file->setUuid('synthetic-file');
		$entity = new IdentifyMethod();
		$entity->setIdentifierKey($method);
		$entity->setIdentifierValue('synthetic-recipient');
		$methodObject = $this->createMock(IIdentifyMethod::class);
		$methodObject->method('getEntity')->willReturn($entity);
		return [$request, $file, $methodObject];
	}

	#[DataProvider('invitationCases')]
	public function testInvitationPreservesGatewayContract(bool $configured, string $method, bool $first): void {
		[$listener, $transport, $mapper] = $this->listener($configured, true, $first);
		[$request, $file, $identify] = $this->invitation($method);
		$mapper->expects($this->once())->method('incrementNotificationCounter')
			->with($request, $method);
		$listener->handle(new SendSignNotificationEvent($request, $file, $identify));
		self::assertCount(1, $transport->sent);
		$record = $transport->sent[0];
		self::assertSame('synthetic-recipient', $record['recipient']);
		self::assertStringContainsString($first ? 'There is a document' : 'Changes have been made', $record['message']);
		if ($configured && $method === 'whatsappbusiness') {
			self::assertSame(2, $record['extra']['expected_body_parameter_count']);
			self::assertStringContainsString('Example.pdf', $record['extra']['body_parameters'][0]);
			self::assertSame('https://example.invalid/libresign.page.sign', $record['extra']['body_parameters'][1]);
		} else {
			self::assertSame([], $record['extra']);
		}
	}

	public static function invitationCases(): array {
		return [
			'business invitation' => [true, 'whatsappbusiness', true],
			'business reminder' => [true, 'whatsappbusiness', false],
			'SMS unchanged' => [true, 'sms', true],
			'business not configured' => [false, 'whatsappbusiness', true],
		];
	}

	public function testSignedEventUsesValidationUrlWithoutIncrementingRequestCounter(): void {
		[$listener, $transport, $mapper] = $this->listener(true, true, false);
		$mapper->expects($this->never())->method('incrementNotificationCounter');
		[$request, $file, $identify] = $this->invitation('whatsappbusiness');
		$listener->handle(new SignedEvent(
			$request, $file, $identify,
			$this->createMock(IUser::class), $this->createMock(NextcloudFile::class),
		));
		self::assertCount(1, $transport->sent);
		self::assertStringContainsString('Synthetic signer signed', $transport->sent[0]['extra']['body_parameters'][0]);
		self::assertSame('https://example.invalid/libresign.page.validationFilePublic', $transport->sent[0]['extra']['body_parameters'][1]);
	}

	public function testDisabledGatewayDoesNotRecordNotificationOrSend(): void {
		[$listener, $transport, $mapper] = $this->listener(true, false, true);
		$mapper->expects($this->never())->method('incrementNotificationCounter');
		[$request, $file, $identify] = $this->invitation('whatsappbusiness');
		$listener->handle(new SendSignNotificationEvent($request, $file, $identify));
		self::assertSame([], $transport->sent);
	}
}

/** Synthetic in-memory direct integration. No HTTP or external provider calls. */
final class GatewayDeliveryRecorder {
	/** @var list<array<string, mixed>> */
	public array $sent = [];

	public function send(string $gateway, string $recipient, string $message, array $extra = []): void {
		$this->sent[] = compact('gateway', 'recipient', 'message', 'extra');
	}
}
