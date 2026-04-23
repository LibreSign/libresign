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
	 * @return array<string, array{0: mixed, 1: array{enabled: bool, writeQrcodeOnFooter: bool, validationSite: string, customizeFooterTemplate: bool, footerTemplate?: string, previewWidth?: int, previewHeight?: int, previewZoom?: int}}>
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
				'{"enabled":true,"writeQrcodeOnFooter":false,"validationSite":"https://validation.example","customizeFooterTemplate":true,"previewWidth":740,"previewHeight":160,"previewZoom":130}',
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => false,
					'validationSite' => 'https://validation.example',
					'customizeFooterTemplate' => true,
					'previewWidth' => 740,
					'previewHeight' => 160,
					'previewZoom' => 130,
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

	#[DataProvider('validationSiteOverrideCases')]
	public function testValidationSiteOverrideRules(
		array $actorCapabilities,
		bool $expectException,
	): void {
		$provider = new FooterPolicy();
		$definition = $provider->get(FooterPolicy::KEY);
		$value = $definition->normalizeValue([
			'enabled' => true,
			'writeQrcodeOnFooter' => true,
			'validationSite' => 'https://internal.example/validation',
			'customizeFooterTemplate' => false,
		]);

		$context = (new PolicyContext())
			->setActorCapabilities($actorCapabilities);

		if ($expectException) {
			$this->expectException(\InvalidArgumentException::class);
		}

		$definition->validateValue($value, $context);

		if (!$expectException) {
			$this->addToAssertionCount(1);
		}
	}

	/**
	 * @return array<string, array{0: array{canManageSystemPolicies: bool, canManageGroupPolicies: bool}, 1: bool}>
	 */
	public static function validationSiteOverrideCases(): array {
		return [
			'rejects override for regular actors' => [
				[
					'canManageSystemPolicies' => false,
					'canManageGroupPolicies' => false,
				],
				true,
			],
			'allows override for policy managers' => [
				[
					'canManageSystemPolicies' => false,
					'canManageGroupPolicies' => true,
				],
				false,
			],
		];
	}
}
