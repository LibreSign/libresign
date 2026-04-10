<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Footer;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\Footer\AddFooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\SignatureFooterPolicyValue;
use PHPUnit\Framework\TestCase;

final class AddFooterPolicyTest extends TestCase {
	public function testProviderBuildsAddFooterDefinition(): void {
		$provider = new AddFooterPolicy();
		$this->assertSame([AddFooterPolicy::KEY], $provider->keys());
		$definition = $provider->get(AddFooterPolicy::KEY);

		$this->assertSame(AddFooterPolicy::KEY, $definition->key());
		$this->assertSame(
			SignatureFooterPolicyValue::encode(SignatureFooterPolicyValue::defaults()),
			$definition->defaultSystemValue(),
		);
		$this->assertSame([], $definition->allowedValues(new PolicyContext()));
	}

	public function testProviderNormalizesBooleanLikeValues(): void {
		$provider = new AddFooterPolicy();
		$definition = $provider->get(AddFooterPolicy::KEY);

		$this->assertSame(
			SignatureFooterPolicyValue::encode([
				'enabled' => true,
				'writeQrcodeOnFooter' => true,
				'validationSite' => '',
				'customizeFooterTemplate' => false,
			]),
			$definition->normalizeValue(true),
		);

		$this->assertSame(
			SignatureFooterPolicyValue::encode([
				'enabled' => false,
				'writeQrcodeOnFooter' => true,
				'validationSite' => '',
				'customizeFooterTemplate' => false,
			]),
			$definition->normalizeValue('0'),
		);

		$this->assertSame(
			SignatureFooterPolicyValue::encode([
				'enabled' => true,
				'writeQrcodeOnFooter' => false,
				'validationSite' => 'https://validation.example',
				'customizeFooterTemplate' => true,
			]),
			$definition->normalizeValue('{"enabled":true,"writeQrcodeOnFooter":false,"validationSite":"https://validation.example","customizeFooterTemplate":true}'),
		);
	}
}
