<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\Footer;

use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FooterPolicyValueTest extends TestCase {
	public function testDefaultsAreStable(): void {
		$this->assertSame([
			'enabled' => true,
			'writeQrcodeOnFooter' => true,
			'validationSite' => '',
			'customizeFooterTemplate' => false,
			'footerTemplate' => '',
			'previewWidth' => 595,
			'previewHeight' => 100,
			'previewZoom' => 100,
		], FooterPolicyValue::defaults());
	}

	#[DataProvider('normalizeCases')]
	public function testNormalize(mixed $input, array $expected): void {
		$this->assertSame($expected, FooterPolicyValue::normalize($input));
	}

	public function testEncodeReturnsCanonicalPayload(): void {
		$this->assertSame(
			'{"enabled":true,"writeQrcodeOnFooter":true,"validationSite":"","customizeFooterTemplate":false,"footerTemplate":"","previewWidth":595,"previewHeight":100,"previewZoom":100}',
			FooterPolicyValue::encode([
				'enabled' => true,
				'writeQrcodeOnFooter' => true,
				'validationSite' => '',
				'customizeFooterTemplate' => false,
			]),
		);
	}

	#[DataProvider('isQrCodeEnabledCases')]
	public function testIsQrCodeEnabled(mixed $input, bool $expected): void {
		$this->assertSame($expected, FooterPolicyValue::isQrCodeEnabled($input));
	}

	/**
	 * @return array<string, array{0: mixed, 1: array{enabled: bool, writeQrcodeOnFooter: bool, validationSite: string, customizeFooterTemplate: bool, footerTemplate: string, previewWidth: int, previewHeight: int, previewZoom: int}}>
	 */
	public static function normalizeCases(): array {
		return [
			'boolean false keeps defaults and disables footer' => [
				false,
				[
					'enabled' => false,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
					'footerTemplate' => '',
					'previewWidth' => 595,
					'previewHeight' => 100,
					'previewZoom' => 100,
				],
			],
			'int one enables footer' => [
				1,
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
					'footerTemplate' => '',
					'previewWidth' => 595,
					'previewHeight' => 100,
					'previewZoom' => 100,
				],
			],
			'legacy scalar off' => [
				'0',
				[
					'enabled' => false,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
					'footerTemplate' => '',
					'previewWidth' => 595,
					'previewHeight' => 100,
					'previewZoom' => 100,
				],
			],
			'legacy scalar yes treated as true' => [
				'yes',
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
					'footerTemplate' => '',
					'previewWidth' => 595,
					'previewHeight' => 100,
					'previewZoom' => 100,
				],
			],
			'structured json payload' => [
				'{"enabled":true,"writeQrcodeOnFooter":false,"validationSite":"https://validation.example","customizeFooterTemplate":true}',
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => false,
					'validationSite' => 'https://validation.example',
					'customizeFooterTemplate' => true,
					'footerTemplate' => '',
					'previewWidth' => 595,
					'previewHeight' => 100,
					'previewZoom' => 100,
				],
			],
			'legacy snake case array payload' => [
				[
					'addFooter' => '1',
					'write_qrcode_on_footer' => '0',
					'validation_site' => ' https://legacy.example/base/ ',
					'customize_footer_template' => '1',
				],
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => false,
					'validationSite' => 'https://legacy.example/base/',
					'customizeFooterTemplate' => true,
					'footerTemplate' => '',
					'previewWidth' => 595,
					'previewHeight' => 100,
					'previewZoom' => 100,
				],
			],
			'array payload with non scalar validation site is sanitized' => [
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => true,
					'validationSite' => ['invalid'],
					'customizeFooterTemplate' => false,
				],
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
					'footerTemplate' => '',
					'previewWidth' => 595,
					'previewHeight' => 100,
					'previewZoom' => 100,
				],
			],
			'empty string returns defaults' => [
				'',
				[
					'enabled' => true,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
					'footerTemplate' => '',
					'previewWidth' => 595,
					'previewHeight' => 100,
					'previewZoom' => 100,
				],
			],
			'invalid json string treated as scalar false' => [
				'{broken-json',
				[
					'enabled' => false,
					'writeQrcodeOnFooter' => true,
					'validationSite' => '',
					'customizeFooterTemplate' => false,
					'footerTemplate' => '',
					'previewWidth' => 595,
					'previewHeight' => 100,
					'previewZoom' => 100,
				],
			],
		];
	}

	/**
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public static function isQrCodeEnabledCases(): array {
		return [
			'legacy scalar one keeps qrcode enabled' => [
				'1',
				true,
			],
			'legacy scalar off disables everything' => [
				'0',
				false,
			],
			'enabled and qr on' => [
				'{"enabled":true,"writeQrcodeOnFooter":true}',
				true,
			],
			'enabled and qr off' => [
				'{"enabled":true,"writeQrcodeOnFooter":false}',
				false,
			],
			'footer disabled even with qr on' => [
				'{"enabled":false,"writeQrcodeOnFooter":true}',
				false,
			],
		];
	}
}
