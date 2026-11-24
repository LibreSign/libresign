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
use OCA\Libresign\Vendor\Symfony\Component\Process\ExecutableFinder;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
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

	#[DataProvider('providerRenderedText')]
	public function testRenderedTextCanBeReadFromPdf(string $text, string $expected, int $width): void {
		$executable = (new ExecutableFinder())->find('pdftotext');
		if ($executable === null) {
			$this->markTestSkipped('Poppler is required to verify rendered PDF text.');
		}

		$signatureTextService = $this->createMock(SignatureTextService::class);
		$signatureTextService->method('parse')->willReturn([
			'parsed' => $text,
			'templateFontSize' => 10.0,
		]);
		$xObject = (new SignatureStampAppearanceBuilder($signatureTextService))->buildXObject(
			$width,
			100,
			SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY,
		);
		$pdf = (new SinglePagePdfExporter())->export(new CompileResult(
			$xObject->stream,
			$xObject->resources ?? [],
			[0.0, 0.0, (float)$width, 100.0],
		));

		$process = new Process([$executable, '-raw', '-nopgbrk', '-enc', 'UTF-8', '-', '-'], input: $pdf);
		$process->mustRun();
		$this->assertSame($expected, trim($process->getOutput()));
	}

	public static function providerRenderedText(): array {
		return [
			'reported French template' => [
				"Signé avec LibreSign\nRenée Dupont\nÉmetteur : Phenix Tech",
				"Signé avec LibreSign\nRenée Dupont\nÉmetteur : Phenix Tech",
				350,
			],
			'canonically decomposed French' => [
				"Signe\u{0301} avec LibreSign\nE\u{0301}metteur : Phenix Tech",
				"Signé avec LibreSign\nÉmetteur : Phenix Tech",
				350,
			],
			'CRLF and CR line endings' => [
				"Signé\r\nÉmetteur\rRenée",
				"Signé\nÉmetteur\nRenée",
				350,
			],
			'Latin letters and typographic symbols' => [
				'ÀÂÄÇÈÉÊËÎÏÔÖÙÛÜŸ àâäçèéêëîïôöùûüÿ Œœ Ææ ß €',
				'ÀÂÄÇÈÉÊËÎÏÔÖÙÛÜŸ àâäçèéêëîïôöùûüÿ Œœ Ææ ß €',
				500,
			],
			'ASCII PDF delimiters' => ['Signed (by) \\ LibreSign', 'Signed (by) \\ LibreSign', 350],
			'Unicode PDF delimiters' => ['Renée (João) \\ Émetteur €', 'Renée (João) \\ Émetteur €', 350],
			'Portuguese' => ['João Gonçalves', 'João Gonçalves', 350],
			'Greek' => ['Αλέξανδρος', 'Αλέξανδρος', 350],
			'Cyrillic' => ['Иван Иванов', 'Иван Иванов', 350],
			'supplementary Unicode scalar' => ['Signer 😀', 'Signer 😀', 350],
			'wide glyphs cannot leave the stamp' => [
				'WÉWÉWÉWÉWÉWÉWÉ',
				"WÉWÉWÉ\nWÉWÉWÉ\nWÉ",
				50,
			],
		];
	}

	#[DataProvider('providerUnicodeWrapping')]
	public function testWrapTextForPdfDoesNotSplitMultibyteCharacters(string $text, array $expected): void {
		$signatureTextService = $this->createMock(SignatureTextService::class);
		$builder = new SignatureStampAppearanceBuilder($signatureTextService);

		$this->assertSame(
			$expected,
			$builder->wrapTextForPdf($text, 15.0, 10.0),
		);
	}

	public static function providerUnicodeWrapping(): array {
		return [
			'precomposed' => ['éééé', ['éé', 'éé']],
			'decomposed' => ["e\u{0301}e\u{0301}e\u{0301}e\u{0301}", ['éé', 'éé']],
			'wide accents' => ['ÉÉÉÉ', ['ÉÉ', 'ÉÉ']],
		];
	}

	#[DataProvider('providerUnsupportedText')]
	public function testBuildXObjectRejectsUnsupportedText(string $text, string $message): void {
		$signatureTextService = $this->createMock(SignatureTextService::class);
		$signatureTextService->method('parse')->willReturn([
			'parsed' => $text,
			'templateFontSize' => 10.0,
		]);
		$builder = new SignatureStampAppearanceBuilder($signatureTextService);

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage($message);

		$builder->buildXObject(240, 50, SignerElementsService::RENDER_MODE_DESCRIPTION_ONLY);
	}

	public static function providerUnsupportedText(): array {
		return [
			'noncharacter' => ["Missing \u{10FFFF}", 'Unicode text requires an embedded TrueType font'],
			'character not covered by shipped font' => ['漢', 'Unicode text requires an embedded TrueType font'],
			'invalid UTF-8' => ["Invalid \xC3", 'Signature stamp text must be valid UTF-8'],
		];
	}
}
