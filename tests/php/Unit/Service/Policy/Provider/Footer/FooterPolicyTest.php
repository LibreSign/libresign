<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Footer;

use OCA\Libresign\Service\Policy\Model\PolicyContext;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FooterPolicyTest extends TestCase {
	public function testProviderBuildsAddFooterDefinition(): void {
		$provider = new FooterPolicy();
		$this->assertSame([FooterPolicy::KEY], $provider->keys());
		$definition = $provider->get(FooterPolicy::KEY);

		$this->assertSame(FooterPolicy::KEY, $definition->key());
		$this->assertSame(
			FooterPolicyValue::encode(FooterPolicyValue::defaults()),
			$definition->defaultSystemValue(),
		);
		$this->assertSame([], $definition->allowedValues(new PolicyContext()));
	}

	#[DataProvider('normalizationCases')]
	public function testProviderNormalizesValues(mixed $input, array $expected): void {
		$provider = new FooterPolicy();
		$definition = $provider->get(FooterPolicy::KEY);

		$this->assertSame(
			FooterPolicyValue::encode($expected),
			$definition->normalizeValue($input),
		);
	}

	/**
	 * @return array<string, array{0: mixed, 1: array{enabled: bool, writeQrcodeOnFooter: bool, validationSite: string, customizeFooterTemplate: bool}}>
	 */
	public static function normalizationCases(): array {
		return [
			'boolean true enables footer with defaults' => [
				true,
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
				],
			],
			'string zero disables footer with defaults' => [
				'0',
				[
					'enabled' => false,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
				],
			],
			'structured json keeps full explicit payload' => [
				'{"enabled":true,"writeQrcodeOnFooter":false,"validationSite":"https://validation.example","customizeFooterTemplate":true}',
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => false,
					'validationSite' => 'https://validation.example',
					'customizeFooterTemplate' => true,
				],
			],
			'legacy snake case json is normalized' => [
				'{"addFooter":"1","write_qrcode_on_footer":"0","validation_site":" https://legacy.example/base/ ","customize_footer_template":"1"}',
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => false,
					'validationSite' => 'https://legacy.example/base/',
					'customizeFooterTemplate' => true,
				],
			],
			'invalid json falls back to enabled false from scalar parser' => [
				'{invalid-json',
				[
					'enabled' => false,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
				],
			],
		];
	}
}
