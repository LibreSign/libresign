<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\IdentifyMethodService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdentifyMethodCatalogTest extends TestCase {
	public function testPhoneMethodsAreSubsetOfTwofactorGatewayMethods(): void {
		$phoneMethods = IdentifyMethodService::IDENTIFY_PHONE_METHODS;
		$twofactorMethods = IdentifyMethodService::IDENTIFY_TWOFACTOR_GATEWAY_METHODS;

		foreach ($phoneMethods as $method) {
			self::assertContains($method, $twofactorMethods);
		}
	}

	public function testTwofactorGatewayMethodsIncludeExpectedMethods(): void {
		self::assertEqualsCanonicalizing([
			IdentifyMethodService::IDENTIFY_SMS,
			IdentifyMethodService::IDENTIFY_SIGNAL,
			IdentifyMethodService::IDENTIFY_TELEGRAM,
			IdentifyMethodService::IDENTIFY_WHATSAPP,
			IdentifyMethodService::IDENTIFY_WHATSAPP_BUSINESS,
			IdentifyMethodService::IDENTIFY_XMPP,
		], IdentifyMethodService::IDENTIFY_TWOFACTOR_GATEWAY_METHODS);
	}

	public function testPhoneMethodsIncludeExpectedMethods(): void {
		self::assertEqualsCanonicalizing([
			IdentifyMethodService::IDENTIFY_WHATSAPP,
			IdentifyMethodService::IDENTIFY_WHATSAPP_BUSINESS,
			IdentifyMethodService::IDENTIFY_SMS,
			IdentifyMethodService::IDENTIFY_TELEGRAM,
			IdentifyMethodService::IDENTIFY_SIGNAL,
		], IdentifyMethodService::IDENTIFY_PHONE_METHODS);
	}

	#[DataProvider('providerGatewayNameMapping')]
	public function testResolveTwofactorGatewayName(string $identifyMethod, string $expectedGatewayName): void {
		self::assertSame(
			$expectedGatewayName,
			IdentifyMethodService::resolveTwofactorGatewayName($identifyMethod)
		);
	}

	public static function providerGatewayNameMapping(): array {
		return [
			'whatsapp legacy gateway name' => [
				IdentifyMethodService::IDENTIFY_WHATSAPP,
				'gowhatsapp',
			],
			'whatsapp business uses provider id directly' => [
				IdentifyMethodService::IDENTIFY_WHATSAPP_BUSINESS,
				'whatsappbusiness',
			],
			'sms uses lowercase id' => [
				IdentifyMethodService::IDENTIFY_SMS,
				'sms',
			],
			'xmpp uses lowercase id' => [
				IdentifyMethodService::IDENTIFY_XMPP,
				'xmpp',
			],
		];
	}
}
