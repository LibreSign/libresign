<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\SignatureStampPreview;

use OCA\Libresign\Service\SignatureStampPreview\SignatureStampAppearanceBuilder;
use OCA\Libresign\Service\SignatureTextService;
use OCA\Libresign\Service\SignerElementsService;
use OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Dto\CompileResult;
use OCA\Libresign\Vendor\LibreSign\XObjectTemplate\Pdf\SinglePagePdfExporter;
use OCA\Libresign\Vendor\SignerPHP\PdfCore\PdfDocument;
use OCA\Libresign\Vendor\SignerPHP\PdfCore\PdfValue\PDFValueReference;
use PHPUnit\Framework\Attributes\DataProvider;

final class SignatureStampAppearanceBuilderTest extends \OCA\Libresign\Tests\Unit\TestCase {
	#[DataProvider('providerUnicodeText')]
	public function testBuildXObjectEmbedsUnicodeText(string $text, string $unicodeHex): void {
		$signatureTextService = $this->createMock(SignatureTextService::class);
		$signatureTextService->method('parse')->willReturn([
			'parsed' => $text,
			'templateFontSize' => 10.0,
		]);
		$builder = new SignatureStampAppearanceBuilder($signatureTextService);

		$xObject = $builder->buildXObject(
			240,
			50,
			SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
		);

		$this->assertNotNull($xObject->resourcesBuilder);
		$this->assertNotNull($xObject->resources);
		$this->assertArrayNotHasKey('XObject', $xObject->resources);
		$document = new PdfDocument();
		$document->setMaxOid(20);
		$materialized = ($xObject->resourcesBuilder)($document, $xObject->resources);
		$this->assertInstanceOf(PDFValueReference::class, $materialized['Font']['U1']);
		$this->assertInstanceOf(PDFValueReference::class, $materialized['Font']['F1']);
		$this->assertGreaterThan(20, $document->getMaxOid());
		$fontObjects = implode("\n", array_map('strval', $document->getPdfObjects()));
		$this->assertTrue(str_contains($fontObjects, '/Identity-H'));
		$this->assertTrue(str_contains($fontObjects, '/ToUnicode'));
		$this->assertStringContainsString('/U1 ', $xObject->stream);
		$this->assertStringNotContainsString($text, $xObject->stream);
		$pdf = (new SinglePagePdfExporter())->export(new CompileResult(
			$xObject->stream,
			$xObject->resources ?? [],
			[0.0, 0.0, 240.0, 50.0],
		));
		$this->assertTrue(str_contains($pdf, '/Encoding /Identity-H'));
		$this->assertTrue(str_contains($pdf, '/FontFile2'));
		$this->assertTrue(str_contains($pdf, '/ToUnicode'));
		$this->assertSame(1, preg_match('/<[0-9A-F]{4}> <' . $unicodeHex . '>/', $pdf));
	}

	public static function providerUnicodeText(): array {
		return [
			'french' => ['Signé par Renée', '00E9'],
			'portuguese' => ['João Gonçalves', '00E3'],
			'greek' => ['Αλέξανδρος', '0391'],
			'cyrillic' => ['Иван Иванов', '0418'],
		];
	}

	public function testWrapTextForPdfDoesNotSplitMultibyteCharacters(): void {
		$signatureTextService = $this->createMock(SignatureTextService::class);
		$builder = new SignatureStampAppearanceBuilder($signatureTextService);

		$this->assertSame(
			['éé', 'éé'],
			$builder->wrapTextForPdf('éééé', 15.0, 10.0),
		);
	}

	public function testBuildXObjectRejectsMissingGlyph(): void {
		$signatureTextService = $this->createMock(SignatureTextService::class);
		$signatureTextService->method('parse')->willReturn([
			'parsed' => "Missing \u{10FFFF}",
			'templateFontSize' => 10.0,
		]);
		$builder = new SignatureStampAppearanceBuilder($signatureTextService);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unicode text requires an embedded TrueType font');

		$builder->buildXObject(240, 50, SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY);
	}
}
