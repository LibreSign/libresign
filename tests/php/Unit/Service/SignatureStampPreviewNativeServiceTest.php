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
		$this->assertStringContainsString('/SMask', $pdf, 'Expected signature image to include alpha mask for transparency');
	}

	public function testRenderPreviewPdfDrawsBackgroundBeforeSignatureInGraphicMode(): void {
		$backgroundBlob = file_get_contents(dirname(__DIR__, 4) . '/img/preview_signature.png');
		$this->assertIsString($backgroundBlob);
		$this->assertNotSame('', $backgroundBlob);

		$this->signatureBackgroundService->expects($this->once())
			->method('getDefaultImageBlob')
			->willReturn($backgroundBlob);

		$pdf = $this->service->renderPreviewPdf(
			template: 'Ignored in graphic mode',
			renderMode: SignerElementsService::RENDER_MODE_GRAPHIC_ONLY,
			backgroundType: 'default',
		);

		$backgroundPosition = strpos($pdf, '/Im1 Do');
		$signaturePosition = strpos($pdf, '/Im2 Do');

		$this->assertNotFalse($backgroundPosition, 'Expected background image command (/Im1 Do) in preview stream');
		$this->assertNotFalse($signaturePosition, 'Expected signature image command (/Im2 Do) in preview stream');
		$this->assertLessThan($signaturePosition, $backgroundPosition, 'Expected background to be drawn before signature');
	}

	public function testRenderPreviewPdfGraphicOnlyCentersWithoutUpscalingWhenAreaIsLarger(): void {
		$pdf = $this->service->renderPreviewPdf(
			template: 'Ignored in graphic mode',
			signatureWidth: 10000,
			signatureHeight: 5000,
			renderMode: SignerElementsService::RENDER_MODE_GRAPHIC_ONLY,
			backgroundType: 'deleted',
		);

		$placement = $this->extractIm2Placement($pdf);
		$imageSize = $this->extractIm2ObjectSize($pdf);

		$this->assertSame($imageSize['width'], $placement['width'], 'Expected graphic-only preview not to upscale signature width');
		$this->assertSame($imageSize['height'], $placement['height'], 'Expected graphic-only preview not to upscale signature height');
		$this->assertSame((int)floor((10000 - $imageSize['width']) / 2), $placement['x']);
		$this->assertSame((int)floor((5000 - $imageSize['height']) / 2), $placement['y']);
	}

	public function testRenderPreviewPdfGraphicOnlyUsesMoreHorizontalSpaceThanGraphicAndDescription(): void {
		$graphicOnlyPdf = $this->service->renderPreviewPdf(
			template: 'Ignored in graphic mode',
			signatureWidth: 120,
			signatureHeight: 80,
			renderMode: SignerElementsService::RENDER_MODE_GRAPHIC_ONLY,
			backgroundType: 'deleted',
		);

		$graphicAndDescriptionPdf = $this->service->renderPreviewPdf(
			template: 'Ignored in graphic mode',
			signatureWidth: 120,
			signatureHeight: 80,
			renderMode: SignerElementsService::RENDER_MODE_GRAPHIC_AND_DESCRIPTION,
			backgroundType: 'deleted',
		);

		$graphicOnlyPlacement = $this->extractIm2Placement($graphicOnlyPdf);
		$graphicAndDescriptionPlacement = $this->extractIm2Placement($graphicAndDescriptionPdf);

		$this->assertTrue(
			$graphicOnlyPlacement['width'] > $graphicAndDescriptionPlacement['width']
			|| $graphicOnlyPlacement['x'] > $graphicAndDescriptionPlacement['x'],
			'Expected graphic-only mode to use full visible width (larger width or wider centering than graphic+description mode)',
		);
	}

	/**
	 * @return array{width:int,height:int,x:int,y:int}
	 */
	private function extractIm2Placement(string $pdf): array {
		$this->assertMatchesRegularExpression('/q\\n(\\d+) 0 0 (\\d+) (\\d+) (\\d+) cm\\n\\/Im2 Do\\nQ/', $pdf);
		preg_match('/q\\n(\\d+) 0 0 (\\d+) (\\d+) (\\d+) cm\\n\\/Im2 Do\\nQ/', $pdf, $matches);

		return [
			'width' => (int)$matches[1],
			'height' => (int)$matches[2],
			'x' => (int)$matches[3],
			'y' => (int)$matches[4],
		];
	}

	/**
	 * @return array{width:int,height:int}
	 */
	private function extractIm2ObjectSize(string $pdf): array {
		preg_match('/\/Im2\s+(\d+)\s+0\s+R/', $pdf, $referenceMatches);
		$im2ObjectId = (int)($referenceMatches[1] ?? 0);
		$this->assertGreaterThan(0, $im2ObjectId, 'Expected a valid Im2 object id');

		preg_match(sprintf('/%d 0 obj\\n<<[^>]*\/Width (\d+) \/Height (\d+)/s', $im2ObjectId), $pdf, $sizeMatches);
		$this->assertNotSame('', ($sizeMatches[1] ?? ''), 'Expected Im2 image width in object dictionary');
		$this->assertNotSame('', ($sizeMatches[2] ?? ''), 'Expected Im2 image height in object dictionary');

		return [
			'width' => (int)$sizeMatches[1],
			'height' => (int)$sizeMatches[2],
		];
	}
}
