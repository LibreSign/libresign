<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Service\FooterService;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicy;
use OCA\Libresign\Service\Policy\Provider\Footer\FooterPolicyValue;
use OCA\Libresign\Tests\Unit\TestCase;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

class FooterServiceTest extends TestCase {
	private IAppConfig $appConfig;
	private FooterHandler|MockObject $footerHandler;
	private FooterService $service;

	public function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->getMockAppConfigWithReset();
		$this->footerHandler = $this->createMock(FooterHandler::class);
		$this->service = new FooterService($this->appConfig, $this->footerHandler);
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

		$this->appConfig->setValueString(Application::APP_ID, FooterPolicy::KEY, $policyValue);

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

		$this->footerHandler
			->method('getEffectiveFooterPolicyAsJson')
			->willReturn($defaultPolicyValue);

		$this->service->saveTemplate($template);

		$this->assertSame(
			$template,
			$this->appConfig->getValueString(Application::APP_ID, 'footer_template')
		);
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

		$this->service->saveTemplate($defaultTemplate);

		$this->assertSame(
			'',
			$this->appConfig->getValueString(Application::APP_ID, 'footer_template', '')
		);
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

		$this->service->saveTemplate($customTemplate);

		$policyPayload = $this->appConfig->getValueString(Application::APP_ID, FooterPolicy::KEY, '');
		$policy = FooterPolicyValue::normalize(json_decode($policyPayload, true));

		$this->assertTrue($policy['customizeFooterTemplate']);
		$this->assertSame($customTemplate, $policy['footerTemplate']);
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

		// Pre-populate with a customized policy
		$this->appConfig->setValueString(
			Application::APP_ID,
			FooterPolicy::KEY,
			FooterPolicyValue::encode([
				'enabled' => true,
				'writeQrcodeOnFooter' => true,
				'validationSite' => '',
				'customizeFooterTemplate' => true,
				'footerTemplate' => '<div>Old Custom Footer</div>',
				'previewWidth' => 595,
				'previewHeight' => 100,
				'previewZoom' => 100,
			])
		);

		$this->footerHandler
			->method('getEffectiveFooterPolicyAsJson')
			->willReturn($defaultPolicyValue);

		$this->service->saveTemplate('');

		$policyPayload = $this->appConfig->getValueString(Application::APP_ID, FooterPolicy::KEY, '');
		$policy = FooterPolicyValue::normalize(json_decode($policyPayload, true));

		$this->assertFalse($policy['customizeFooterTemplate']);
		$this->assertSame('', $policy['footerTemplate']);
	}

	public function testGetTemplate(): void {
		$template = '<div>Custom template</div>';
		$this->appConfig->setValueString(Application::APP_ID, 'footer_template', $template);

		$this->footerHandler
			->expects($this->once())
			->method('getTemplate')
			->willReturn($template);

		$this->assertSame($template, $this->service->getTemplate());
	}

	public function testGetTemplateReturnsDefaultWhenNotSet(): void {
		$this->appConfig->deleteKey(Application::APP_ID, 'footer_template');

		$defaultTemplate = '<div>Default footer template</div>';
		$this->footerHandler
			->expects($this->once())
			->method('getTemplate')
			->willReturn($defaultTemplate);

		$this->assertSame($defaultTemplate, $this->service->getTemplate());
	}

	#[DataProvider('provideRenderPreviewPdfScenarios')]
	public function testRenderPreviewPdf(?string $template, int $width, int $height, bool $shouldSaveTemplate): void {
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

		if ($shouldSaveTemplate) {
			$this->assertSame(
				$template,
				$this->appConfig->getValueString(Application::APP_ID, 'footer_template')
			);
		}
	}

	public function testRenderPreviewPdfWithWriteQrcodeOverrideTrue(): void {
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
			'with custom template and default dimensions' => ['<div>Custom</div>', 595, 50, true],
			'without template uses default' => ['', 595, 50, false],
			'with custom dimensions' => ['<div>Test</div>', 800, 100, true],
			'A4 width with custom height' => ['', 595, 75, false],
			'empty string template' => ['', 595, 50, false],
			'template with unicode characters' => ['<div>签名 подпись توقيع</div>', 595, 50, true],
			'minimum dimensions' => ['<div>Min</div>', 1, 1, true],
			'large dimensions' => ['', 2000, 500, false],
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
