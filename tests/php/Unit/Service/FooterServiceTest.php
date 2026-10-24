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
	public function testIsDefaultTemplate(string $configValue, bool $expected): void {
		$this->appConfig->setValueString(Application::APP_ID, 'footer_template', $configValue);

		$this->assertSame($expected, $this->service->isDefaultTemplate());
	}

	public static function provideIsDefaultTemplateScenarios(): array {
		return [
			'empty string is default' => ['', true],
			'custom template is not default' => ['<div>Custom</div>', false],
			'whitespace only is not default' => ['   ', false],
		];
	}

	public function testSaveTemplate(): void {
		$template = '<div>My custom template</div>';

		$this->service->saveTemplate($template);

		$this->assertSame(
			$template,
			$this->appConfig->getValueString(Application::APP_ID, 'footer_template')
		);
	}

	public function testSaveTemplateEqualToDefaultDeletesKey(): void {
		$defaultTemplate = '<div>Default Footer</div>';

		$this->footerHandler
			->expects($this->once())
			->method('getDefaultTemplate')
			->willReturn($defaultTemplate);

		$this->service->saveTemplate($defaultTemplate);

		$this->assertSame(
			'',
			$this->appConfig->getValueString(Application::APP_ID, 'footer_template', '')
		);
	}

	public function testUnsafeFooterTemplateDoesNotOverwriteExistingSettings(): void {
		$existing = '<div>Approved</div>';
		$this->appConfig->setValueString(Application::APP_ID, 'footer_template', $existing);
		try {
			$this->service->saveTemplate('{{ range(1, 3) }}');
			$this->fail('Unsafe Twig template must not be persisted');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('range', $e->getMessage());
		}
		$this->assertSame($existing, $this->appConfig->getValueString(Application::APP_ID, 'footer_template'));
	}

	public function testResetCanRemoveAnExistingUnsafeFooterTemplate(): void {
		$this->appConfig->setValueString(Application::APP_ID, 'footer_template', '{{ range(1, 3) }}');
		$this->service->saveTemplate('');
		$this->assertSame('', $this->appConfig->getValueString(Application::APP_ID, 'footer_template', ''));
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
	public function testRenderPreviewPdf(?string $template, int $width, int $height): void {
		$persistedTemplate = '<div>Persisted</div>';
		$this->appConfig->setValueString(Application::APP_ID, 'footer_template', $persistedTemplate);
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
			->with([['w' => $width, 'h' => $height]], $template ?: null)
			->willReturn('PDF binary content');

		$result = $this->service->renderPreviewPdf($template, $width, $height);

		$this->assertSame('PDF binary content', $result);

		$this->assertSame(
			$persistedTemplate,
			$this->appConfig->getValueString(Application::APP_ID, 'footer_template'),
		);
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
