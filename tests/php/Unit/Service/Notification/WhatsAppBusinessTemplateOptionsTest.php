<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Notification;

use OCA\Libresign\Service\Notification\WhatsAppBusinessTemplateOptions;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WhatsAppBusinessTemplateOptionsTest extends TestCase {
	private function options(array $settings): WhatsAppBusinessTemplateOptions {
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => $settings[$key] ?? $default,
		);
		return new WhatsAppBusinessTemplateOptions($config);
	}

	public function testConfiguredTemplateGetsOrderedTextAndUrl(): void {
		$options = $this->options([
			'whatsapp_business_template_name' => 'synthetic_utility',
			'whatsapp_business_template_language' => 'pt_BR',
		]);
		$extra = $options->forNotification("  Documento\npara assinatura\tpendente  ", 'https://example.invalid/sign');
		$this->assertSame(['Documento para assinatura pendente', 'https://example.invalid/sign'], $extra['body_parameters']);
		$this->assertSame('synthetic_utility', $extra['template_name']);
		$this->assertSame('UTILITY', $extra['template_category']);
	}

	public function testWithoutSeparateTemplateConfigurationDoesNotChangeDelivery(): void {
		$this->assertSame([], $this->options([])->forNotification('Notification', 'https://example.invalid/sign'));
	}

	#[DataProvider('invalidConfigurationOrContent')]
	public function testRejectsInvalidConfigurationOrMessageBeforeDelivery(
		array $settings, string $text, string $url,
	): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->options($settings)->forNotification($text, $url);
	}

	public static function invalidConfigurationOrContent(): array {
		$config = [
			'whatsapp_business_template_name' => 'synthetic_utility',
			'whatsapp_business_template_language' => 'pt_BR',
		];
		return [
			'only template name configured' => [['whatsapp_business_template_name' => 'synthetic_utility'], 'Hi', 'https://example.invalid/sign'],
			'only language configured' => [['whatsapp_business_template_language' => 'pt_BR'], 'Hi', 'https://example.invalid/sign'],
			'template name with unsafe characters' => [['whatsapp_business_template_name' => "synthetic\nunsafe", 'whatsapp_business_template_language' => 'pt_BR'], 'Hi', 'https://example.invalid/sign'],
			'template language with unsafe characters' => [['whatsapp_business_template_name' => 'synthetic_utility', 'whatsapp_business_template_language' => 'pt_BR/unsafe'], 'Hi', 'https://example.invalid/sign'],
			'blank message' => [$config, " \n ", 'https://example.invalid/sign'],
			'invalid unicode in message' => [$config, "\xFF", 'https://example.invalid/sign'],
			'oversized message' => [$config, str_repeat('a', 1025), 'https://example.invalid/sign'],
			'insecure URL' => [$config, 'Hi', 'http://example.invalid/sign'],
			'non-absolute URL' => [$config, 'Hi', '/sign/request'],
			'URL containing credentials' => [$config, 'Hi', 'https://user:password@example.invalid/sign'],
		];
	}
}
