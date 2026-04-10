<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Footer;

use OCA\Libresign\Service\Policy\Provider\Footer\SignatureFooterPolicyValue;
use PHPUnit\Framework\TestCase;

final class SignatureFooterPolicyValueTest extends TestCase {
	public function testDefaultsAreStable(): void {
		$this->assertSame([
			'enabled' => true,
			'writeQrcodeOnFooter' => true,
			'validationSite' => '',
			'customizeFooterTemplate' => false,
		], SignatureFooterPolicyValue::defaults());
	}

	public function testNormalizeLegacyBooleanInput(): void {
		$this->assertSame([
			'enabled' => false,
			'writeQrcodeOnFooter' => true,
			'validationSite' => '',
			'customizeFooterTemplate' => false,
		], SignatureFooterPolicyValue::normalize('0'));
	}

	public function testNormalizeStructuredJsonInput(): void {
		$normalized = SignatureFooterPolicyValue::normalize(
			'{"enabled":true,"writeQrcodeOnFooter":false,"validationSite":"https://validation.example","customizeFooterTemplate":true}'
		);

		$this->assertSame([
			'enabled' => true,
			'writeQrcodeOnFooter' => false,
			'validationSite' => 'https://validation.example',
			'customizeFooterTemplate' => true,
		], $normalized);
	}

	public function testEncodeReturnsCanonicalPayload(): void {
		$this->assertSame(
			'{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false}',
			SignatureFooterPolicyValue::encode([
				'enabled' => true,
				'writeQrcodeOnFooter' => true,
				'validationSite' => '',
				'customizeFooterTemplate' => false,
			]),
		);
	}
}
