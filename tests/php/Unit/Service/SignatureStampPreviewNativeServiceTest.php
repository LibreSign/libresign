<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service;

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Libresign\Service\SignatureBackgroundService;
use OCA\Libresign\Service\SignatureStampPreview\SignatureStampAppearanceBuilder;
use OCA\Libresign\Service\SignatureStampPreview\SignatureStampPreviewNativeService;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use PHPUnit\Framework\MockObject\MockObject;

final class SignatureStampPreviewNativeServiceTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private SignatureTextService&MockObject $signatureTextService;
	private SignatureBackgroundService&MockObject $signatureBackgroundService;
	private SignatureStampPreviewNativeService $service;

	public function setUp(): void {
		parent::setUp();
		$this->signatureTextService = $this->createMock(SignatureTextService::class);
		$this->signatureBackgroundService = $this->createMock(SignatureBackgroundService::class);
		$this->signatureTextService->method('getPreviewSignerName')->willReturn('Preview Signer');
		$this->signatureTextService->method('getTemplateFontSize')->willReturn(10.0);
		$this->signatureTextService->method('getSignatureFontSize')->willReturn(20.0);
		$this->signatureTextService->method('parse')->willReturn([
			'parsed' => 'Signed by Preview Signer',
			'templateFontSize' => 10.0,
		]);
		$this->signatureBackgroundService->method('getImage')->willThrowException(new \Exception('no background for test'));

		$builder = new SignatureStampAppearanceBuilder($this->signatureTextService);
		$this->service = new SignatureStampPreviewNativeService($builder, $this->signatureTextService, $this->signatureBackgroundService);
	}

	public function testRenderPreviewPdfReturnsValidPdfHeader(): void {
		$pdf = $this->service->renderPreviewPdf(
			template: 'Signed by {{ SignerCommonName }}',
			renderMode: SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
		);

		$this->assertStringStartsWith('%PDF-1.4', $pdf);
		$this->assertStringContainsString('/Type /Page', $pdf);
		$this->assertStringContainsString('/Helvetica', $pdf);
	}

	public function testRenderPreviewPdfUsesPreviewDummyContextForTemplateVariables(): void {
		$capturedContext = null;
		$this->signatureTextService->expects($this->once())
			->method('parse')
			->with(
				'{{SignerCommonName}} | {{IssuerCommonName}} | {{SignerIP}} | {{SignerUserAgent}} | {{SignerIdentifier}} | {{DocumentUUID}}',
				$this->callback(function (array $context) use (&$capturedContext): bool {
					$capturedContext = $context;
					return true;
				}),
			)
			->willReturn([
				'parsed' => 'Preview content',
				'templateFontSize' => 10.0,
			]);

		$this->service->renderPreviewPdf(
			template: '{{SignerCommonName}} | {{IssuerCommonName}} | {{SignerIP}} | {{SignerUserAgent}} | {{SignerIdentifier}} | {{DocumentUUID}}',
			renderMode: SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
		);

		$this->assertIsArray($capturedContext);
		$this->assertSame('Preview Signer', $capturedContext['SignerCommonName']);
		$this->assertSame('Preview Issuer', $capturedContext['IssuerCommonName']);
		$this->assertSame('192.0.2.10', $capturedContext['SignerIP']);
		$this->assertSame('LibreSign Preview Browser', $capturedContext['SignerUserAgent']);
		$this->assertSame('preview-signer', $capturedContext['SignerIdentifier']);
		$this->assertSame('11111111-2222-4333-8444-555555555555', $capturedContext['DocumentUUID']);
	}

	public function testRenderPreviewPdfEmbedssignatureImageInGraphicModes(): void {
		$pdf = $this->service->renderPreviewPdf(
			template: 'Ignored in graphic mode',
			renderMode: SignerElementsService::RENDER_MODE_GRAPHIC_ONLY,
		);

		// The preview signature asset should be embedded as XObject Im2 in GRAPHIC_ONLY mode
		$this->assertStringContainsString('/Im2 Do', $pdf);
		$this->assertMatchesRegularExpression('/\/Im2\s+(\d+)\s+0\s+R/', $pdf, 'Expected Im2 XObject reference in page resources');

		preg_match('/\/Im2\s+(\d+)\s+0\s+R/', $pdf, $matches);
		$im2ObjectId = (int)($matches[1] ?? 0);
		$this->assertGreaterThan(0, $im2ObjectId, 'Expected a valid Im2 object id');
		$this->assertStringContainsString(
			sprintf("%d 0 obj\n<< /Type /XObject /Subtype /Image", $im2ObjectId),
			$pdf,
			'Expected Im2 object to be an image XObject',
		);
	}
}
