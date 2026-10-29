<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Db\File;
use OCA\Libresign\Db\SignRequest;
use OCA\Libresign\Service\Notification\GatewayNotificationComposer;
use OCA\Libresign\Service\Notification\WhatsAppBusinessTemplateOptions;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GatewayNotificationComposerTest extends TestCase {
	private function composer(bool $template = true, ?callable $translate = null): GatewayNotificationComposer {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback($translate
			?? static fn (string $text, array $parameters = []): string => vsprintf($text, $parameters));
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->willReturnCallback(
			static fn (string $route): string => 'https://example.invalid/' . $route,
		);
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string
				=> ($template ? [
					'whatsapp_business_template_name' => 'synthetic_utility',
					'whatsapp_business_template_language' => 'pt_BR',
				] : [])[$key] ?? $default,
		);
		return new GatewayNotificationComposer($l10n, $urls, new WhatsAppBusinessTemplateOptions($config));
	}

	/** @return array{SignRequest, File} */
	private function document(?string $description = 'Synthetic description'): array {
		$request = new SignRequest();
		$request->setUuid('synthetic-request');
		$request->setDescription($description);
		$request->setDisplayName('Synthetic signer');
		$file = new File();
		$file->setName('Example.pdf');
		$file->setUuid('synthetic-file');
		return [$request, $file];
	}

	#[DataProvider('notifications')]
	public function testConfiguredWhatsAppUtilityTemplateUsesOrderedContentAndUrl(
		string $event, bool $first, string $expectedText, string $expectedRoute,
	): void {
		[$request, $file] = $this->document();
		$composer = $this->composer();
		$payload = $event === 'sign_request'
			? $composer->composeSignRequest($request, $file, $first, 'whatsappbusiness')
			: $composer->composeSignedFile($request, $file, 'whatsappbusiness');

		self::assertSame('whatsappbusiness', $payload->gatewayName);
		self::assertSame('synthetic_utility', $payload->extra['template_name']);
		self::assertSame('UTILITY', $payload->extra['template_category']);
		self::assertSame(2, $payload->extra['expected_body_parameter_count']);
		self::assertStringContainsString($expectedText, $payload->extra['body_parameters'][0]);
		self::assertStringContainsString('Example.pdf', $payload->extra['body_parameters'][0]);
		self::assertDoesNotMatchRegularExpression('/[\r\n\t]/', $payload->extra['body_parameters'][0]);
		self::assertStringContainsString($expectedRoute, $payload->extra['body_parameters'][1]);
		self::assertStringContainsString($payload->extra['body_parameters'][1], $payload->body);
	}

	public static function notifications(): array {
		return [
			'initial invitation' => ['sign_request', true, 'There is a document', 'libresign.page.sign'],
			'updated invitation' => ['sign_request', false, 'Changes have been made', 'libresign.page.sign'],
			'signed document' => ['file_signed', true, 'Synthetic signer signed', 'libresign.page.validationFilePublic'],
		];
	}

	#[DataProvider('otherTransports')]
	public function testOtherChannelsAndUnconfiguredBusinessKeepTextOnlyMessages(string $gateway, bool $template): void {
		[$request, $file] = $this->document();
		$payload = $this->composer($template)->composeSignRequest($request, $file, true, $gateway);
		self::assertSame([], $payload->extra);
		self::assertStringContainsString('Example.pdf: https://example.invalid/', $payload->body);
		self::assertStringContainsString("Synthetic description\n\n", $payload->body);
	}

	public static function otherTransports(): array {
		return [
			'SMS' => ['sms', true],
			'Signal' => ['signal', true],
			'WhatsApp Business without template override' => ['whatsappbusiness', false],
		];
	}

	public function testReorderedTranslationKeepsUrlWithinTheLocalizedSentence(): void {
		[$request, $file] = $this->document();
		$translator = static function (string $text, array $params = []): string {
			if (str_contains($text, 'signed the document') && count($params) === 3) {
				return vsprintf('URL %3$s | DOCUMENT %2$s | SIGNER %1$s', $params);
			}
			return vsprintf($text, $params);
		};
		$payload = $this->composer(false, $translator)->composeSignedFile($request, $file, 'sms');
		self::assertStringStartsWith('URL https://example.invalid/', $payload->body);
		self::assertStringContainsString('SIGNER Synthetic signer', $payload->body);
	}
}
