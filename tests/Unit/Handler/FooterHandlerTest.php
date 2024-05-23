<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Handler\FooterHandler;
use OCA\Libresign\Service\PdfParserService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;

final class FooterHandlerTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private IAppConfig&MockObject $appConfig;
	private PdfParserService&MockObject $pdfParserService;
	private IURLGenerator&MockObject $urlGenerator;
	private IL10N&MockObject $l10n;
	private ITempManager&MockObject $tempManager;
	private FooterHandler $footerHandler;
	public function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->pdfParserService = $this->createMock(PdfParserService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->tempManager = $this->createMock(ITempManager::class);

		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->method('t')
			->will($this->returnArgument(0));
	}

	private function getClass(): FooterHandler {
		$this->footerHandler = new FooterHandler(
			$this->appConfig,
			$this->pdfParserService,
			$this->urlGenerator,
			$this->l10n,
			$this->tempManager,
		);
		return $this->footerHandler;
	}

	public function testGetFooterWithoutValidationSite(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig
			->method('getAppValue')
			->willReturn('');
		$file = $this->createMock(\OCP\Files\File::class);
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$actual = $this->getClass()->getFooter($file, $libresignFile);
		$this->assertEmpty($actual);
	}

	public function testGetFooterWithSuccess(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appConfig
			->method('getAppValue')
			->willReturnCallback(function ($key, $default):string {
				return match ($key) {
					'add_footer' => '1',
					'validation_site' => 'http://test.coop',
					'write_qrcode_on_footer' => '1',
					'footer_link_to_site' => 'https://libresign.coop',
					'footer_signed_by' => 'Digital signed by LibreSign.',
					'footer_validate_in' => 'Validate in %s.',
					'footer_template' => <<<'HTML'
						<div style="font-size:8px;">
						qrcodeSize:{{ qrcodeSize }}<br />
						signedBy:{{ signedBy }}<br />
						validateIn:{{ validateIn }}<br />
						test:{{ test }}<br />
						qrcode:{{ qrcode }}
						</div>
						HTML,
					default => '',
				};
			});
		$this->tempManager
			->method('getTempBaseDir')
			->willReturn(sys_get_temp_dir());

		$file = $this->createMock(\OCP\Files\File::class);
		$libresignFile = $this->createMock(\OCA\Libresign\Db\File::class);
		$libresignFile
			->method('__call')
			->willReturnCallback(function ($key, $default):array|string {
				return match ($key) {
					'getMetadata' => [
						'd' => [
							[
								'w' => 842,
								'h' => 595,
							],
						],
					],
					'getUuid' => 'uuid',
					default => '',
				};
			});
		$pdf = $this->getClass()
			->setTemplateVar('test', 'fake value')
			->getFooter($file, $libresignFile);
		$actual = $this->extractPdfContent($pdf, [
			'qrcodeSize',
			'signedBy',
			'validateIn',
			'test',
			'qrcode',
		]);
		$expected = [
			'qrcodeSize' => '108',
			'signedBy' => 'Digital signed by LibreSign.',
			'validateIn' => 'Validate in %s.',
			'test' => 'fake value',
		];
		$this->assertArrayHasKey('qrcode', $actual);
		unset($actual['qrcode']);
		$this->assertEquals($expected, $actual);
	}

	private function extractPdfContent(string $content, array $keys): array {
		$this->assertNotEmpty($content, 'Empty PDF file');
		$this->assertNotEmpty($keys, 'Is necessary to send a not empty array of fields to search at PDF file');
		$parser = new \Smalot\PdfParser\Parser();
		$pdf = $parser->parseContent($content);
		$text = $pdf->getText();
		$this->assertNotEmpty($text, 'PDF without text');
		$content = explode("\n", $text);
		$this->assertNotEmpty($content, 'PDF without any row');
		$content = array_map(fn ($row) => str_getcsv($row, ':'), $content);
		$content = array_filter($content, fn ($row) => in_array($row[0], $keys));
		$this->assertNotEmpty($content, 'Fields not found at PDF file');
		return array_column($content, 1, 0);
	}
}
