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
use OCA\Libresign\Vendor\Symfony\Component\Process\ExecutableFinder;
use OCA\Libresign\Vendor\Symfony\Component\Process\Process;
use PHPUnit\Framework\Attributes\DataProvider;

final class SignatureStampAppearanceBuilderTest extends \OCA\Libresign\Tests\Unit\TestCase {
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
			'literal text is not reinterpreted' => [
				'Renée <name> & {{ literal }}',
				'Renée <name> & {{ literal }}',
				350,
			],
		];
	}

}
