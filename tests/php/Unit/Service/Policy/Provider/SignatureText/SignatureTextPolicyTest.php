<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Policy\Provider\SignatureText;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicy;
use OCA\Libresign\Service\Policy\Provider\SignatureText\SignatureTextPolicyValue;
use OCA\Libresign\Service\SignatureTextTemplate;
use OCP\L10N\IFactory as IL10NFactory;
use Test\TestCase;

class SignatureTextPolicyTest extends TestCase {
	private SignatureTextPolicy $policy;

	public function setUp(): void {
		parent::setUp();
		$l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->policy = new SignatureTextPolicy($l10n);
	}

	public function testKeysReturnsAllPolicyKeys(): void {
		$keys = $this->policy->keys();
		$this->assertCount(7, $keys);
		$this->assertContains(SignatureTextPolicy::KEY, $keys);
		$this->assertContains(SignatureTextPolicy::KEY_TEMPLATE, $keys);
		$this->assertContains(SignatureTextPolicy::KEY_TEMPLATE_FONT_SIZE, $keys);
		$this->assertContains(SignatureTextPolicy::KEY_SIGNATURE_WIDTH, $keys);
		$this->assertContains(SignatureTextPolicy::KEY_SIGNATURE_HEIGHT, $keys);
		$this->assertContains(SignatureTextPolicy::KEY_SIGNATURE_FONT_SIZE, $keys);
		$this->assertContains(SignatureTextPolicy::KEY_RENDER_MODE, $keys);
	}

	public function testGetTemplatePolicy(): void {
		$spec = $this->policy->get(SignatureTextPolicy::KEY_TEMPLATE);
		$this->assertEquals(SignatureTextPolicy::KEY_TEMPLATE, $spec->key());
		$l10n = \OCP\Server::get(IL10NFactory::class)->get(Application::APP_ID);
		$this->assertEquals(SignatureTextTemplate::translated($l10n, false), $spec->defaultSystemValue());
		$this->assertEmpty($spec->allowedValues(new \OCA\Libresign\Service\Policy\Model\PolicyContext()));
		$this->assertEquals('', $spec->normalizeValue(''));
		$this->assertEquals('test template', $spec->normalizeValue('test template'));
		$this->assertEquals(SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE, $spec->getAppConfigKey());
	}

	public function testConsolidatedPolicyExposesCanonicalDefaultInResolvedMeta(): void {
		$spec = $this->policy->get(SignatureTextPolicy::KEY);
		$resolvedStateMeta = $spec->resolvedStateMeta(new \OCA\Libresign\Service\Policy\Model\PolicyContext());

		$this->assertSame($spec->defaultSystemValue(), $resolvedStateMeta['defaultSystemValue']);
	}

	public function testGetTemplateFontSizePolicy(): void {
		$spec = $this->policy->get(SignatureTextPolicy::KEY_TEMPLATE_FONT_SIZE);
		$this->assertEquals(SignatureTextPolicy::KEY_TEMPLATE_FONT_SIZE, $spec->key());
		$this->assertEquals(SignatureTextPolicyValue::DEFAULT_TEMPLATE_FONT_SIZE, $spec->defaultSystemValue());
		$this->assertEmpty($spec->allowedValues(new \OCA\Libresign\Service\Policy\Model\PolicyContext()));
		$this->assertEquals(8.5, $spec->normalizeValue('8.5'));
		$this->assertEquals(10.0, $spec->normalizeValue(10));
		$this->assertEquals(SignatureTextPolicy::SYSTEM_APP_CONFIG_KEY_TEMPLATE_FONT_SIZE, $spec->getAppConfigKey());
	}

	public function testGetSignatureWidthPolicy(): void {
		$spec = $this->policy->get(SignatureTextPolicy::KEY_SIGNATURE_WIDTH);
		$this->assertEquals(SignatureTextPolicy::KEY_SIGNATURE_WIDTH, $spec->key());
		$this->assertEquals(SignatureTextPolicyValue::DEFAULT_SIGNATURE_WIDTH, $spec->defaultSystemValue());
		$this->assertEmpty($spec->allowedValues(new \OCA\Libresign\Service\Policy\Model\PolicyContext()));
		$this->assertEquals(SignatureTextPolicyValue::DEFAULT_SIGNATURE_WIDTH, $spec->normalizeValue(350));
		$this->assertEquals(120.5, $spec->normalizeValue('120.5'));
	}

	public function testGetSignatureHeightPolicy(): void {
		$spec = $this->policy->get(SignatureTextPolicy::KEY_SIGNATURE_HEIGHT);
		$this->assertEquals(SignatureTextPolicy::KEY_SIGNATURE_HEIGHT, $spec->key());
		$this->assertEquals(SignatureTextPolicyValue::DEFAULT_SIGNATURE_HEIGHT, $spec->defaultSystemValue());
		$this->assertEmpty($spec->allowedValues(new \OCA\Libresign\Service\Policy\Model\PolicyContext()));
		$this->assertEquals(SignatureTextPolicyValue::DEFAULT_SIGNATURE_HEIGHT, $spec->normalizeValue(100));
	}

	public function testGetSignatureFontSizePolicy(): void {
		$spec = $this->policy->get(SignatureTextPolicy::KEY_SIGNATURE_FONT_SIZE);
		$this->assertEquals(SignatureTextPolicy::KEY_SIGNATURE_FONT_SIZE, $spec->key());
		$this->assertEquals(SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE, $spec->defaultSystemValue());
		$this->assertEmpty($spec->allowedValues(new \OCA\Libresign\Service\Policy\Model\PolicyContext()));
		$this->assertEquals(SignatureTextPolicyValue::DEFAULT_SIGNATURE_FONT_SIZE, $spec->normalizeValue(20));
		$this->assertEquals(11.5, $spec->normalizeValue('11.5'));
	}

	public function testGetRenderModePolicy(): void {
		$spec = $this->policy->get(SignatureTextPolicy::KEY_RENDER_MODE);
		$this->assertEquals(SignatureTextPolicy::KEY_RENDER_MODE, $spec->key());
		$this->assertEquals('default', $spec->defaultSystemValue());
		$allowedValues = $spec->allowedValues(new \OCA\Libresign\Service\Policy\Model\PolicyContext());
		$this->assertCount(3, $allowedValues);
		$this->assertContains('default', $allowedValues);
		$this->assertContains('graphic', $allowedValues);
		$this->assertContains('text', $allowedValues);
	}

	public function testRenderModeNormalizerAcceptsValidValues(): void {
		$spec = $this->policy->get(SignatureTextPolicy::KEY_RENDER_MODE);
		$this->assertEquals('default', $spec->normalizeValue('default'));
		$this->assertEquals('graphic', $spec->normalizeValue('graphic'));
		$this->assertEquals('text', $spec->normalizeValue('text'));
	}

	public function testRenderModeNormalizerFallsBackToDefaultForInvalid(): void {
		$spec = $this->policy->get(SignatureTextPolicy::KEY_RENDER_MODE);
		$this->assertEquals('default', $spec->normalizeValue('invalid'));
		$this->assertEquals('default', $spec->normalizeValue(''));
		$this->assertEquals('default', $spec->normalizeValue('unknown_mode'));
	}

	public function testGetWithInvalidKeyThrows(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown policy key: invalid_key');
		$this->policy->get('invalid_key');
	}

	public function testLegacySignatureTextKeyIsNotAccepted(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown policy key: signature_text');
		$this->policy->get('signature_text');
	}
}
