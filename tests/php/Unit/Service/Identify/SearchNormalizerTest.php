<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Identify;

use OCA\Libresign\Service\Identify\SearchNormalizer;
use OCP\IConfig;
use OCP\IPhoneNumberUtil;
use OCP\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SearchNormalizerTest extends TestCase {
	private SearchNormalizer $normalizer;
	private IConfig&MockObject $config;
	private IPhoneNumberUtil $phoneNumberUtil;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->phoneNumberUtil = Server::get(IPhoneNumberUtil::class);
		$this->normalizer = new SearchNormalizer($this->config, $this->phoneNumberUtil);
	}

	#[DataProvider('providerNormalizeScenarios')]
	public function testNormalize(
		string $input,
		string $method,
		string $defaultRegion,
		string $expected,
		string $description,
	): void {
		$this->config->method('getSystemValueString')
			->with('default_phone_region', '')
			->willReturn($defaultRegion);

		$result = $this->normalizer->normalize($input, $method);
		$this->assertEquals($expected, $result, $description);
	}

	public static function providerNormalizeScenarios(): array {
		return [
			// Non-phone methods pass through unchanged
			['test@example.com', 'email', 'BR', 'test@example.com', 'Non-phone method (email) passes through'],
			['john_doe', 'account', 'BR', 'john_doe', 'Non-phone method (account) passes through'],
			['pass123', 'password', 'BR', 'pass123', 'Non-phone method (password) passes through'],

			// International format passes through unchanged
			['+5521969501266', 'sms', 'BR', '+5521969501266', 'International format (BR) passes through'],
			['+12025551234', 'whatsapp', 'US', '+12025551234', 'International format (US) passes through'],
			['+4930123456', 'telegram', 'DE', '+4930123456', 'International format (DE) passes through'],

			// Without region, returns original
			['21969501266', 'sms', '', '21969501266', 'Without region returns original'],

			// Valid numbers normalize correctly
			['21969501266', 'sms', 'BR', '+5521969501266', 'BR mobile normalizes'],
			['11987654321', 'whatsapp', 'BR', '+5511987654321', 'BR São Paulo normalizes'],
			['2025551234', 'telegram', 'US', '+12025551234', 'US number normalizes'],
			['4155551234', 'signal', 'US', '+14155551234', 'US California normalizes'],
			['30123456', 'sms', 'DE', '+4930123456', 'DE Berlin normalizes'],
			['123456789', 'whatsapp', 'FR', '+33123456789', 'FR number normalizes'],

			// Invalid numbers return original
			['123', 'whatsapp', 'BR', '123', 'Too short returns original'],
			['999999999', 'sms', 'BR', '999999999', 'BR without DDD returns original'],

			// All phone methods work identically
			['21987776666', 'whatsapp', 'BR', '+5521987776666', 'WhatsApp normalizes'],
			['21987776666', 'sms', 'BR', '+5521987776666', 'SMS normalizes'],
			['21987776666', 'telegram', 'BR', '+5521987776666', 'Telegram normalizes'],
			['21987776666', 'signal', 'BR', '+5521987776666', 'Signal normalizes'],
		];
	}

	#[DataProvider('providerTryNormalizePhoneNumberScenarios')]
	public function testTryNormalizePhoneNumber(
		string $input,
		string $method,
		string $defaultRegion,
		?string $expected,
		string $description,
	): void {
		$this->config->method('getSystemValueString')
			->with('default_phone_region', '')
			->willReturn($defaultRegion);

		$result = $this->normalizer->tryNormalizePhoneNumber($input, $method);
		$this->assertSame($expected, $result, $description);
	}

	public static function providerTryNormalizePhoneNumberScenarios(): array {
		return [
			// Non-phone methods return null (difference from normalize())
			['test@example.com', 'email', 'BR', null, 'Non-phone method (email) returns null'],
			['john_doe', 'account', 'BR', null, 'Non-phone method (account) returns null'],
			['pass123', 'password', 'BR', null, 'Non-phone method (password) returns null'],

			// Empty/whitespace returns null (difference from normalize())
			['', 'sms', 'BR', null, 'Empty string returns null'],
			['   ', 'whatsapp', 'BR', null, 'Whitespace returns null'],
			['  ', 'telegram', 'US', null, 'Spaces return null'],

			// International format passes through
			['+5521969501266', 'sms', 'BR', '+5521969501266', 'International format (BR) passes through'],
			['+12025551234', 'whatsapp', 'US', '+12025551234', 'International format (US) passes through'],
			['+4930123456', 'telegram', 'DE', '+4930123456', 'International format (DE) passes through'],

			// Valid normalization with region
			['21969501266', 'sms', 'BR', '+5521969501266', 'BR mobile normalizes'],
			['11987654321', 'whatsapp', 'BR', '+5511987654321', 'BR São Paulo normalizes'],
			['2025551234', 'telegram', 'US', '+12025551234', 'US number normalizes'],
			['4155551234', 'signal', 'US', '+14155551234', 'US California normalizes'],
			['30123456', 'sms', 'DE', '+4930123456', 'DE Berlin normalizes'],
			['123456789', 'whatsapp', 'FR', '+33123456789', 'FR number normalizes'],

			// Without region returns null (difference from normalize())
			['21969501266', 'sms', '', null, 'Without region returns null'],
			['2025551234', 'whatsapp', '', null, 'US number without region returns null'],
			['11987654321', 'telegram', '', null, 'BR number without region returns null'],

			// Invalid numbers return null (difference from normalize())
			['999999999', 'sms', 'BR', null, 'BR without DDD returns null'],
			['12345', 'whatsapp', 'BR', null, 'Too short returns null'],
			['123', 'telegram', 'US', null, 'Very short returns null'],
			['00000000', 'signal', 'BR', null, 'Invalid pattern returns null'],

			// All phone methods work identically
			['21987776666', 'whatsapp', 'BR', '+5521987776666', 'WhatsApp normalizes'],
			['21987776666', 'sms', 'BR', '+5521987776666', 'SMS normalizes'],
			['21987776666', 'telegram', 'BR', '+5521987776666', 'Telegram normalizes'],
			['21987776666', 'signal', 'BR', '+5521987776666', 'Signal normalizes'],

			// Edge cases
			['+1234', 'sms', 'BR', '+1234', 'Short international format passes through'],
			['  21969501266  ', 'sms', 'BR', '+5521969501266', 'Trimmed number normalizes'],
		];
	}
}
