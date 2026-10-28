<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Db\SignRequestMapper;
use OCA\Libresign\Service\Notification\GatewayChannel;
use OCA\Libresign\Service\Notification\GatewayNotificationComposer;
use OCA\Libresign\Service\Notification\NotificationHistoryService;
use OCA\Libresign\Service\Notification\NotificationMessage;
use OCA\Libresign\Service\Notification\WhatsAppBusinessTemplateOptions;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/** Optional cross-app contract test with no external requests. */
final class TwofactorGatewayWhatsAppIntegrationTest extends TestCase {
	private function requireGatewayApp(): void {
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
	}

	private function fakeHttpClient(string $expectedRoute): IClient {
		$client = $this->createMock(IClient::class);
		$client->expects($this->once())->method('post')->with(
			$this->anything(),
			$this->callback(function (array $options) use ($expectedRoute): bool {
				$template = $options['json']['template'];
				$this->assertSame('synthetic_utility', $template['name']);
				$this->assertSame('pt_BR', $template['language']['code']);
				$this->assertCount(1, $template['components']);
				$this->assertStringContainsString('Example.pdf', $template['components'][0]['parameters'][0]['text']);
				$this->assertStringContainsString($expectedRoute, $template['components'][0]['parameters'][1]['text']);
				return true;
			}),
		)->willReturnCallback(function (): IResponse {
			$stream = $this->createStub(StreamInterface::class);
			$stream->method('__toString')->willReturn('{"messages":[{"id":"synthetic-id"}]}');
			$response = $this->createStub(IResponse::class);
			$response->method('getBody')->willReturn($stream);
			return $response;
		});
		return $client;
	}

	private function channel(IClient $client): GatewayChannel {
		$http = $this->createMock(IClientService::class);
		$http->method('newClient')->willReturn($client);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $value): string => $value);
		$provider = new Gateway($this->createMock(IAppConfig::class), $http, $l10n, $this->createMock(LoggerInterface::class));
		$provider = $provider->withRuntimeConfig([
			'phone_number_id' => 'synthetic-phone',
			'access_token' => 'synthetic-token',
			'template_name' => 'administrative_otp',
			'template_language' => 'en_US',
			'template_category' => 'AUTHENTICATION',
		]);
		$availability = $this->createMock(GatewayRuntimeAvailabilityService::class);
		$availability->method('getGateway')->with('whatsappbusiness')->willReturn($provider);
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn(new GatewayDirectIntegrationService($availability));
		$enabled = $this->createMock(IAppManager::class);
		$enabled->method('isEnabledForAnyone')->willReturn(true);
		$gateway = new TwofactorGatewayService($container, $enabled, $this->createMock(LoggerInterface::class));
		return new GatewayChannel($gateway);
	}

	private function compose(string $event): NotificationMessage {
		$request = new SignRequest();
		$request->setId(42);
		$request->setUuid('synthetic-request');
		$request->setDisplayName('Synthetic signer');
		$file = new File();
		$file->setName('Example.pdf');
		$file->setUuid('synthetic-file');
		$mapper = $this->createMock(SignRequestMapper::class);
		$mapper->method('getByIdUncached')->willReturn(new SignRequest());
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $values = []): string
			=> $values === [] ? $text : vsprintf($text, $values));
		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRouteAbsolute')->willReturnCallback(
			static fn (string $route): string => 'https://example.invalid/' . $route,
		);
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => [
				'whatsapp_business_template_name' => 'synthetic_utility',
				'whatsapp_business_template_language' => 'pt_BR',
			][$key] ?? $default,
		);
		$composer = new GatewayNotificationComposer(
			new NotificationHistoryService($mapper), $l10n, $url, new WhatsAppBusinessTemplateOptions($config),
		);
		return new NotificationMessage('synthetic-event', $event, 'gateway:whatsappbusiness',
			$file, $request, '5511999990000',
			$composer->composePayload($event, $request, $file, 'whatsappbusiness', 'whatsappbusiness'),
		);
	}

	#[DataProvider('eventCases')]
	public function testDirectGatewayOverridesDefaultOtpWithoutNetwork(string $event, string $route): void {
		$this->requireGatewayApp();
		$this->channel($this->fakeHttpClient($route))->deliver($this->compose($event));
	}

	public static function eventCases(): array {
		return [
			'invitation' => ['sign_request', 'libresign.page.sign'],
			'completion' => ['file_signed', 'libresign.page.validationFilePublic'],
		];
	}
}
