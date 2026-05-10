<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\IdentifyMethods;

use OCA\Libresign\Service\Policy\Provider\IdentifyMethods\IdentifyMethodsPolicyValue;
use PHPUnit\Framework\TestCase;

final class IdentifyMethodsPolicyValueTest extends TestCase {
	public function testDerivesCanonicalRequirementFromLegacyMandatory(): void {
		$normalized = IdentifyMethodsPolicyValue::normalize([
			[
				'name' => 'email',
				'enabled' => true,
				'mandatory' => true,
				'signatureMethods' => ['emailToken'],
			],
		]);

		self::assertSame([
			'factors' => [
				[
					'name' => 'email',
					'enabled' => true,
					'signatureMethods' => [
						'emailToken' => ['enabled' => false],
					],
					'requirement' => 'required',
					'mandatory' => true,
				],
			],
		], $normalized);
	}

	public function testPreservesCanonicalRequirementAndKeepsCompatibilityMirror(): void {
		$normalized = IdentifyMethodsPolicyValue::normalize([
			[
				'name' => 'whatsapp',
				'enabled' => true,
				'requirement' => 'optional',
				'mandatory' => true,
				'minimumTotalVerifiedFactors' => 2,
				'signatureMethods' => [
					'whatsappToken' => ['enabled' => true],
				],
			],
		]);

		self::assertSame([
			'factors' => [
				[
					'name' => 'whatsapp',
					'enabled' => true,
					'signatureMethods' => [
						'whatsappToken' => ['enabled' => true],
					],
					'minimumTotalVerifiedFactors' => 2,
					'requirement' => 'optional',
					'mandatory' => false,
				],
			],
		], $normalized);
	}

	public function testNormalizesSharedMinimumTotalVerifiedFactorsFromObjectPayload(): void {
		$normalized = IdentifyMethodsPolicyValue::normalize([
			'minimumTotalVerifiedFactors' => 2,
			'factors' => [
				[
					'name' => 'email',
					'enabled' => true,
					'mandatory' => true,
					'signatureMethods' => ['emailToken'],
				],
			],
		]);

		self::assertSame([
			'factors' => [
				[
					'name' => 'email',
					'enabled' => true,
					'signatureMethods' => [
						'emailToken' => ['enabled' => false],
					],
					'minimumTotalVerifiedFactors' => 2,
					'requirement' => 'required',
					'mandatory' => true,
				],
			],
		], $normalized);
	}

	public function testDefaultsEnabledToTrueWhenPayloadOmitsIt(): void {
		$normalized = IdentifyMethodsPolicyValue::normalize([
			[
				'name' => 'email',
				'signatureMethods' => ['emailToken'],
			],
		]);

		self::assertSame([
			'factors' => [
				[
					'name' => 'email',
					'enabled' => true,
					'signatureMethods' => [
						'emailToken' => ['enabled' => false],
					],
				],
			],
		], $normalized);
	}

	public function testAcceptsSharedMinimumTotalVerifiedFactorsAsNumericString(): void {
		$normalized = IdentifyMethodsPolicyValue::normalize([
			'minimumTotalVerifiedFactors' => '2',
			'factors' => [
				[
					'name' => 'sms',
					'enabled' => true,
					'signatureMethods' => ['smsToken'],
				],
			],
		]);

		self::assertSame([
			'factors' => [
				[
					'name' => 'sms',
					'enabled' => true,
					'signatureMethods' => [
						'smsToken' => ['enabled' => false],
					],
					'minimumTotalVerifiedFactors' => 2,
				],
			],
		], $normalized);
	}

	public function testNormalizesLegacyStringListEntries(): void {
		$normalized = IdentifyMethodsPolicyValue::normalize([
			'email',
			'sms',
		]);

		self::assertSame([
			'factors' => [
				[
					'name' => 'email',
					'enabled' => true,
					'signatureMethods' => [],
				],
				[
					'name' => 'sms',
					'enabled' => true,
					'signatureMethods' => [],
				],
			],
		], $normalized);
	}

	public function testNormalizesGlobalCanCreateAccountFromLegacyFactorSettings(): void {
		$normalized = IdentifyMethodsPolicyValue::normalize([
			[
				'name' => 'email',
				'enabled' => true,
				'can_create_account' => false,
				'signatureMethods' => ['emailToken'],
			],
		]);

		self::assertSame(false, $normalized['can_create_account']);
		self::assertArrayNotHasKey('can_create_account', $normalized['factors'][0]);
	}
}
