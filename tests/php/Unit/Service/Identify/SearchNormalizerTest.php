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

	public function testNonPhoneMethodPassesThroughUnchanged(): void {
		$result = $this->normalizer->normalize('test@example.com', 'email');
		$this->assertEquals('test@example.com', $result);
	}

	public function testPhoneAlreadyInInternationalFormatPassesThroughUnchanged(): void {
		$result = $this->normalizer->normalize('+5521969501266', 'sms');
		$this->assertEquals('+5521969501266', $result);
	}

	public function testPhoneWithoutRegionConfigurationPassesThroughUnchanged(): void {
		$this->config->expects($this->once())
			->method('getSystemValueString')
			->with('default_phone_region', '')
			->willReturn('');

		$result = $this->normalizer->normalize('21969501266', 'sms');
		$this->assertEquals('21969501266', $result);
	}

	#[DataProvider('providerValidPhoneNumbersNormalizedCorrectly')]
	public function testValidPhoneNumbersNormalizedToE164Format(string $input, string $region, string $expected): void {
		$this->config->expects($this->once())
			->method('getSystemValueString')
			->with('default_phone_region', '')
			->willReturn($region);

		$result = $this->normalizer->normalize($input, 'sms');
		$this->assertEquals($expected, $result);
	}

	public static function providerValidPhoneNumbersNormalizedCorrectly(): array {
		return [
			'Brazil mobile' => ['21969501266', 'BR', '+5521969501266'],
			'USA mobile' => ['2025551234', 'US', '+12025551234'],
			'Germany mobile' => ['30123456', 'DE', '+4930123456'],
			'France landline' => ['123456789', 'FR', '+33123456789'],
			'Australia number' => ['212345678', 'AU', '+61212345678'],
		];
	}

	public function testInvalidPhoneNumbersReturnEmptyString(): void {
		$this->config->expects($this->once())
			->method('getSystemValueString')
			->with('default_phone_region', '')
			->willReturn('BR');

		$result = $this->normalizer->normalize('123', 'whatsapp');
		$this->assertEquals('', $result);
	}

	#[DataProvider('providerAllPhoneMethodsNormalizeIdentically')]
	public function testAllPhoneMethodsUseNormalization(string $method): void {
		$this->config->expects($this->once())
			->method('getSystemValueString')
			->with('default_phone_region', '')
			->willReturn('BR');

		$result = $this->normalizer->normalize('21987776666', $method);
		$this->assertEquals('+5521987776666', $result);
	}

	public static function providerAllPhoneMethodsNormalizeIdentically(): array {
		return [
			['whatsapp'],
			['sms'],
			['telegram'],
			['signal'],
		];
	}
}
