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
			[
				'name' => 'email',
				'enabled' => true,
				'signatureMethods' => [
					'emailToken' => ['enabled' => false],
				],
				'requirement' => 'required',
				'mandatory' => true,
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
		], $normalized);
	}
}
