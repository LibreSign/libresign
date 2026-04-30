<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Service\FooterService;
use OCA\Libresign\Service\Policy\Model\PolicyLayer;
use OCA\Libresign\Service\Policy\Model\ResolvedPolicy;
use OCA\Libresign\Service\Policy\PolicyService;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class FooterServiceTest extends TestCase {
	private PolicyService|MockObject $policyService;
	private FooterHandler|MockObject $footerHandler;
	private FooterService $service;

	public function setUp(): void {
		parent::setUp();
		$this->policyService = $this->createMock(PolicyService::class);
		$this->footerHandler = $this->createMock(FooterHandler::class);
		$this->service = new FooterService($this->policyService, $this->footerHandler);
	}

	#[DataProvider('provideIsDefaultTemplateScenarios')]
	public function testIsDefaultTemplate(bool $customizeFooterTemplate, bool $expected): void {
		// Setup: create a policy value with the specified customizeFooterTemplate flag
		$policyValue = FooterPolicyValue::encode([
			'enabled' => true,
			'writeQrcodeOnFooter' => true,
			'validationSite' => '',
			'customizeFooterTemplate' => $customizeFooterTemplate,
			'footerTemplate' => $customizeFooterTemplate ? '<div>Custom</div>' : '',
			'previewWidth' => 595,
			'previewHeight' => 100,
			'previewZoom' => 100,
		]);

		// Mock FooterHandler to return the policy JSON
		$this->footerHandler
			->method('getEffectiveFooterPolicyAsJson')
			->willReturn($policyValue);

		$this->assertSame($expected, $this->service->isDefaultTemplate());
	}

	public static function provideIsDefaultTemplateScenarios(): array {
		return [
			'customizeFooterTemplate=false is default' => [false, true],
			'customizeFooterTemplate=true is not default' => [true, false],
		];
	}

	public function testSaveTemplate(): void {
		$template = '<div>My custom template</div>';
		$defaultPolicyValue = FooterPolicyValue::encode(FooterPolicyValue::defaults());
		$savedValue = null;

		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with(
				FooterPolicy::KEY,
				$this->callback(function (string $value) use (&$savedValue): bool {
					$savedValue = $value;
					return true;
				}),
				false,
			)
			->willReturn(new ResolvedPolicy());

		$this->policyService
			->method('getSystemPolicy')
			->willReturn(null);

		$this->footerHandler
			->method('getEffectiveFooterPolicyAsJson')
			->willReturn($defaultPolicyValue);

		$this->service->saveTemplate($template);

		$normalized = FooterPolicyValue::normalize(json_decode((string)$savedValue, true));
		$this->assertTrue($normalized['customizeFooterTemplate']);
		$this->assertSame($template, $normalized['footerTemplate']);
	}

	public function testSaveTemplateEqualToDefaultDeletesKey(): void {
		$defaultTemplate = '<div>Default Footer</div>';
		$defaultPolicyValue = FooterPolicyValue::encode([
			'enabled' => true,
			'writeQrcodeOnFooter' => true,
			'validationSite' => '',
			'customizeFooterTemplate' => false,
			'footerTemplate' => $defaultTemplate,
			'previewWidth' => 595,
			'previewHeight' => 100,
			'previewZoom' => 100,
		]);

		$this->footerHandler
			->method('getEffectiveFooterPolicyAsJson')
			->willReturn($defaultPolicyValue);

		$this->footerHandler
			->method('getDefaultTemplate')
			->willReturn($defaultTemplate);

		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with(
				FooterPolicy::KEY,
				$this->callback(function (string $value): bool {
					$normalized = FooterPolicyValue::normalize(json_decode($value, true));
					return $normalized['customizeFooterTemplate'] === false
						&& $normalized['footerTemplate'] === '';
				}),
				false,
			)
			->willReturn(new ResolvedPolicy());

		$this->policyService
			->method('getSystemPolicy')
			->willReturn(null);

		$this->service->saveTemplate($defaultTemplate);
	}

	public function testSaveTemplateSynchronizesFooterPolicyWhenCustomTemplateIsSaved(): void {
		$defaultTemplate = '<div>Default Footer</div>';
		$customTemplate = '<div>Custom Footer</div>';
		$defaultPolicyValue = FooterPolicyValue::encode([
			'enabled' => true,
			'writeQrcodeOnFooter' => true,
			'validationSite' => '',
			'customizeFooterTemplate' => false,
			'footerTemplate' => $defaultTemplate,
			'previewWidth' => 595,
			'previewHeight' => 100,
			'previewZoom' => 100,
		]);

		$this->footerHandler
			->method('getEffectiveFooterPolicyAsJson')
			->willReturn($defaultPolicyValue);

		$this->footerHandler
			->method('getDefaultTemplate')
			->willReturn($defaultTemplate);

		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with(
				FooterPolicy::KEY,
				$this->callback(function (string $value) use ($customTemplate): bool {
					$policy = FooterPolicyValue::normalize(json_decode($value, true));
					return $policy['customizeFooterTemplate'] === true
						&& $policy['footerTemplate'] === $customTemplate;
				}),
				false,
			)
			->willReturn(new ResolvedPolicy());

		$this->policyService
			->method('getSystemPolicy')
			->willReturn(null);

		$this->service->saveTemplate($customTemplate);
	}

	public function testSaveTemplateSynchronizesFooterPolicyWhenTemplateIsReset(): void {
		$defaultTemplate = '<div>Default Footer</div>';
		$defaultPolicyValue = FooterPolicyValue::encode([
			'enabled' => true,
			'writeQrcodeOnFooter' => true,
			'validationSite' => '',
			'customizeFooterTemplate' => false,
			'footerTemplate' => $defaultTemplate,
			'previewWidth' => 595,
			'previewHeight' => 100,
			'previewZoom' => 100,
		]);

		$this->footerHandler
			->method('getEffectiveFooterPolicyAsJson')
			->willReturn($defaultPolicyValue);

		$this->footerHandler
			->method('getDefaultTemplate')
			->willReturn($defaultTemplate);

		$layer = new PolicyLayer();
		$layer->setAllowChildOverride(true);

		$this->policyService
			->method('getSystemPolicy')
			->willReturn($layer);

		$this->policyService
			->expects($this->once())
			->method('saveSystem')
			->with(
				FooterPolicy::KEY,
				$this->callback(function (string $value): bool {
					$policy = FooterPolicyValue::normalize(json_decode($value, true));
					return $policy['customizeFooterTemplate'] === false
						&& $policy['footerTemplate'] === '';
				}),
				true,
			)
			->willReturn(new ResolvedPolicy());

		$this->service->saveTemplate('');
	}

	public function testGetTemplate(): void {
		$template = '<div>Custom template</div>';

		$this->footerHandler
			->expects($this->once())
			->method('getTemplate')
			->willReturn($template);

		$this->assertSame($template, $this->service->getTemplate());
	}

	public function testGetTemplateReturnsDefaultWhenNotSet(): void {
		$defaultTemplate = '<div>Default footer template</div>';
		$this->footerHandler
			->expects($this->once())
			->method('getTemplate')
			->willReturn($defaultTemplate);

		$this->assertSame($defaultTemplate, $this->service->getTemplate());
	}

	#[DataProvider('provideRenderPreviewPdfScenarios')]
	public function testRenderPreviewPdf(?string $template, int $width, int $height): void {
		$this->footerHandler
			->expects($this->once())
			->method('setTemplateOverride')
			->with($template !== '' ? $template : null)
			->willReturn($this->footerHandler);

		$this->footerHandler
			->expects($this->never())
			->method('getEffectiveFooterPolicyAsJson');

		$this->policyService
			->expects($this->never())
			->method('saveSystem');

		$this->footerHandler
			->expects($this->exactly(2))
			->method('setTemplateVar')
			->willReturnCallback(function ($key, $value) {
				if ($key === 'uuid') {
					$this->assertMatchesRegularExpression('/^preview-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $value);
				} elseif ($key === 'signers') {
					$this->assertIsArray($value);
					$this->assertCount(1, $value);
					$this->assertSame('Preview Signer', $value[0]['displayName']);
					$this->assertArrayHasKey('signed', $value[0]);
				}
				return $this->footerHandler;
			});

		$this->footerHandler
			->expects($this->once())
			->method('getFooter')
			->with([['w' => $width, 'h' => $height]], true)
			->willReturn('PDF binary content');

		$result = $this->service->renderPreviewPdf($template, $width, $height);

		$this->assertSame('PDF binary content', $result);
	}

	public function testRenderPreviewPdfWithWriteQrcodeOverrideTrue(): void {
		$this->footerHandler
			->expects($this->once())
			->method('setTemplateOverride')
			->with(null)
			->willReturn($this->footerHandler);

		$this->footerHandler
			->expects($this->exactly(2))
			->method('setTemplateVar')
			->willReturn($this->footerHandler);

		$this->footerHandler
			->expects($this->once())
			->method('setWriteQrcodeOnFooterOverride')
			->with(true)
			->willReturn($this->footerHandler);

		$this->footerHandler
			->expects($this->once())
			->method('getFooter')
			->willReturn('PDF binary content');

		$this->service->renderPreviewPdf('', 595, 50, true);
	}

	public function testRenderPreviewPdfWithWriteQrcodeOverrideFalse(): void {
		$this->footerHandler
			->expects($this->once())
			->method('setTemplateOverride')
			->with(null)
			->willReturn($this->footerHandler);

		$this->footerHandler
			->expects($this->exactly(2))
			->method('setTemplateVar')
			->willReturn($this->footerHandler);

		$this->footerHandler
			->expects($this->once())
			->method('setWriteQrcodeOnFooterOverride')
			->with(false)
			->willReturn($this->footerHandler);

		$this->footerHandler
			->expects($this->once())
			->method('getFooter')
			->willReturn('PDF binary content');

		$this->service->renderPreviewPdf('', 595, 50, false);
	}

	public function testRenderPreviewPdfWithWriteQrcodeOverrideNull(): void {
		$this->footerHandler
			->expects($this->once())
			->method('setTemplateOverride')
			->with(null)
			->willReturn($this->footerHandler);

		$this->footerHandler
			->expects($this->exactly(2))
			->method('setTemplateVar')
			->willReturn($this->footerHandler);

		$this->footerHandler
			->expects($this->never())
			->method('setWriteQrcodeOnFooterOverride');

		$this->footerHandler
			->expects($this->once())
			->method('getFooter')
			->willReturn('PDF binary content');

		$this->service->renderPreviewPdf('', 595, 50, null);
	}

	public static function provideRenderPreviewPdfScenarios(): array {
		return [
			'with custom template and default dimensions' => ['<div>Custom</div>', 595, 50],
			'without template uses default' => ['', 595, 50],
			'with custom dimensions' => ['<div>Test</div>', 800, 100],
			'A4 width with custom height' => ['', 595, 75],
			'empty string template' => ['', 595, 50],
			'template with unicode characters' => ['<div>签名 подпись توقيع</div>', 595, 50],
			'minimum dimensions' => ['<div>Min</div>', 1, 1],
			'large dimensions' => ['', 2000, 500],
		];
	}

	public function testGetTemplateVariablesMetadata(): void {
		$expectedMetadata = [
			'direction' => [
				'type' => 'string',
				'description' => 'Text direction for the footer',
				'example' => 'ltr',
			],
			'uuid' => [
				'type' => 'string',
				'description' => 'Document unique identifier',
				'example' => 'de0a18d4-fe65-4abc-bdd1-84e819700260',
			],
		];

		$this->footerHandler
			->expects($this->once())
			->method('getTemplateVariablesMetadata')
			->willReturn($expectedMetadata);

		$result = $this->service->getTemplateVariablesMetadata();

		$this->assertSame($expectedMetadata, $result);
	}
}
