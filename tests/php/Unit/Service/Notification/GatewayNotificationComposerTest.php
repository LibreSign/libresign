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
use OCA\Libresign\Service\Notification\GatewayNotificationComposer;
use OCA\Libresign\Service\Notification\NotificationHistoryService;
use OCA\Libresign\Service\Notification\WhatsAppBusinessTemplateOptions;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GatewayNotificationComposerTest extends TestCase {
	private function composer(bool $first = true, array $settings = []): GatewayNotificationComposer {
		$stored = new SignRequest();
		$stored->setMetadata($first ? [] : ['notify' => [['method' => 'whatsappbusiness']]]);
		$mapper = $this->createMock(SignRequestMapper::class);
		$mapper->method('getByIdUncached')->willReturn($stored);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string
			=> $params === [] ? $text : vsprintf($text, $params));
		$url = $this->createMock(IURLGenerator::class);
		$url->method('linkToRouteAbsolute')->willReturnCallback(
			static fn (string $route, array $params): string => 'https://example.invalid/' . $route . '/' . $params['uuid'],
		);
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => $settings[$key] ?? $default,
		);
		return new GatewayNotificationComposer(
			new NotificationHistoryService($mapper), $l10n, $url, new WhatsAppBusinessTemplateOptions($config),
		);
	}

	/** @return array{SignRequest, File} */
	private function messageData(): array {
		$request = new SignRequest();
		$request->setId(42);
		$request->setUuid('synthetic-request');
		$request->setDescription('Synthetic description');
		$request->setDisplayName('Synthetic signer');
		$file = new File();
		$file->setName('Example.pdf');
		$file->setUuid('synthetic-file');
		return [$request, $file];
	}

	private function settings(): array {
		return [
			'whatsapp_business_template_name' => 'synthetic_utility',
			'whatsapp_business_template_language' => 'pt_BR',
		];
	}

	#[DataProvider('eventCases')]
	public function testConfiguredUtilityContractHasOrderedValues(
		string $event, bool $first, string $fragment, string $route,
	): void {
		[$request, $file] = $this->messageData();
		$composer = $this->composer($first, $this->settings());
		$payload = $composer->composePayload($event, $request, $file, 'whatsappbusiness', 'whatsappbusiness');
		$this->assertSame('synthetic_utility', $payload->extra['template_name']);
		$this->assertSame('pt_BR', $payload->extra['template_language']);
		$this->assertSame('UTILITY', $payload->extra['template_category']);
		$this->assertSame(2, $payload->extra['expected_body_parameter_count']);
		$this->assertStringContainsString($fragment, $payload->extra['body_parameters'][0]);
		$this->assertStringContainsString('Example.pdf', $payload->extra['body_parameters'][0]);
		$this->assertDoesNotMatchRegularExpression('/[\r\n\t]/', $payload->extra['body_parameters'][0]);
		$this->assertStringContainsString($route, $payload->extra['body_parameters'][1]);
		$this->assertSame($composer->compose($event, $request, $file, 'whatsappbusiness'), $payload->body);
	}

	public static function eventCases(): array {
		return [
			'initial request' => ['sign_request', true, 'There is a document', 'libresign.page.sign'],
			'updated request' => ['sign_request', false, 'Changes have been made', 'libresign.page.sign'],
			'signed file' => ['file_signed', true, 'Synthetic signer signed', 'libresign.page.validationFilePublic'],
		];
	}

	#[DataProvider('legacyCases')]
	public function testAbsentTemplateConfigKeepsExistingMessageDelivery(string $gateway): void {
		[$request, $file] = $this->messageData();
		$composer = $this->composer();
		$payload = $composer->composePayload('sign_request', $request, $file, $gateway, $gateway);
		$this->assertSame([], $payload->extra);
		$this->assertSame($composer->compose('sign_request', $request, $file, $gateway), $payload->body);
	}

	public static function legacyCases(): array {
		return ['sms' => ['sms'], 'whatsappbusiness' => ['whatsappbusiness']];
	}

	public function testIncompleteTemplateConfigFailsWithoutSilentFallback(): void {
		[$request, $file] = $this->messageData();
		$composer = $this->composer(true, ['whatsapp_business_template_name' => 'synthetic_utility']);
		$this->expectException(\InvalidArgumentException::class);
		$composer->composePayload('sign_request', $request, $file, 'whatsappbusiness', 'whatsappbusiness');
	}
}
