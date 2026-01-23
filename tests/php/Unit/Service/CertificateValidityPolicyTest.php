<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Service\CertificateValidityPolicy;
use OCA\Libresign\Service\IdentifyMethod\SignatureMethod\ISignatureMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CertificateValidityPolicyTest extends TestCase {
	public static function leafExpiryProvider(): array {
		return [
			'click-to-sign without password' => [
				ISignatureMethod::SIGNATURE_METHOD_CLICK_TO_SIGN,
				true,
				1,
			],
			'click-to-sign with password' => [
				ISignatureMethod::SIGNATURE_METHOD_CLICK_TO_SIGN,
				false,
				null,
			],
			'other method without password' => [
				'password',
				true,
				null,
			],
		];
	}

	#[DataProvider('leafExpiryProvider')]
	public function testGetLeafExpiryDays(?string $signatureMethodName, bool $signWithoutPassword, ?int $expected): void {
		$policy = new CertificateValidityPolicy();

		$result = $policy->getLeafExpiryDays($signatureMethodName, $signWithoutPassword);

		$this->assertSame($expected, $result);
	}
}
