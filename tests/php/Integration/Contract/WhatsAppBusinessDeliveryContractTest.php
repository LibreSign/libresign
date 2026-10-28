<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Contract;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\Notification\GatewayChannel;
use OCA\Libresign\Service\Notification\GatewayNotificationComposer;
use OCA\Libresign\Service\Notification\NotificationHistoryService;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\TwofactorGatewayService;
use OCA\TwoFactorGateway\Provider\Channel\WhatsApp\Provider\Drivers\WhatsAppBusiness\Gateway;
use OCA\TwoFactorGateway\Service\GatewayDirectIntegrationService;
use OCA\TwoFactorGateway\Service\GatewayRuntimeAvailabilityService;
use OCP\App\IAppManager;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/** Optional cross-app contract test. Never sends an actual WhatsApp message. */
final class TwofactorGatewayWhatsAppIntegrationTest extends TestCase {
	public function testOrderedNotificationValuesReachTheMockedHttpClient(): void {
		$appManager = \OCP\Server::get(IAppManager::class);
		try {
			$appManager->getAppPath('twofactor_gateway');
		} catch (\OCP\App\AppPathNotFoundException) {
			$this->markTestSkipped('Two-Factor Gateway app source is not installed');
		}
		$appManager->loadApp('twofactor_gateway');
		if (!class_exists(Gateway::class)) {
			$this->markTestSkipped('WhatsApp Business driver is not available');
		}

		$stored = [];
		$config = $this->createMock(IAppConfig::class);
		$config->method('setValueString')->willReturnCallback(static function (string $app, string $key, string $value) use (&$stored): bool {
			$stored[$key] = $value;
			return true;
		});
		$config->method('getValueString')->willReturnCallback(static function (string $app, string $key, string $default = '') use (&$stored): string {
			return $stored[$key] ?? $default;
		});

		$client = $this->createMock(IClient::class);
		$client->expects($this->once())->method('post')->with(
			$this->anything(),
			$this->callback(function (array $options): bool {
				$body = $options['json']['template']['components'][0]['parameters'];
				$this->assertCount(2, $body);
				$this->assertStringContainsString('Example.pdf', $body[0]['text']);
				$this->assertSame('https://example.invalid/sign', $body[1]['text']);
				$this->assertSame('5511999990000', $options['json']['to']);
				return true;
			}),
		)->willReturnCallback(function (): IResponse {
			$stream = $this->createStub(StreamInterface::class);
			$stream->method('__toString')->willReturn('{"messages":[{"id":"synthetic-id"}]}');
			$response = $this->createStub(IResponse::class);
			$response->method('getBody')->willReturn($stream);
			return $response;
		});
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $message): string => $message);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->never())->method('warning');

		$provider = new Gateway($config, $clientService, $l10n, $logger);
		$provider->setPhoneNumberId('synthetic-phone');
		$provider->setAccessToken('synthetic-credential');
		$provider->setTemplateName('synthetic_utility');
		$provider->setTemplateLanguage('pt_BR');
		$provider->setTemplateCategory('UTILITY');
		$provider->setTemplateBodyParameterCount('2');

		$availability = $this->createMock(GatewayRuntimeAvailabilityService::class);
		$availability->method('getGateway')->with('whatsappbusiness')->willReturn($provider);
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn(new GatewayDirectIntegrationService($availability));
		$enabled = $this->createMock(IAppManager::class);
		$enabled->method('isEnabledForAnyone')->willReturn(true);
		$service = new TwofactorGatewayService($container, $enabled, $logger);

		$request = new SignRequest();
		$request->setId(42);
		$request->setUuid('synthetic-request');
		$file = new File();
		$file->setName('Example.pdf');
		$file->setUuid('synthetic-file');
		$mapper = $this->createMock(SignRequestMapper::class);
		$mapper->method('getByIdUncached')->willReturn(new SignRequest());
		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRouteAbsolute')->willReturn('https://example.invalid/sign');
		$composer = new GatewayNotificationComposer(new NotificationHistoryService($mapper), $l10n, $url);
		$payload = $composer->composePayload('sign_request', $request, $file, 'whatsappbusiness', 'whatsappbusiness');

		(new GatewayChannel($service))->deliver(new NotificationMessage(
			'synthetic-event', 'sign_request', 'gateway:whatsappbusiness',
			$file, $request, '5511999990000', $payload,
		));
	}
}
