<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Handler\SignEngine\SignEngineFactory;
use OCA\Libresign\Service\PdfSignatureDetectionService;
use OCA\Libresign\Tests\Fixtures\PdfFixtureCatalog;
use OCA\Libresign\Tests\Fixtures\PdfGenerator;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

class PdfSignatureDetectionServiceTest extends TestCase {

	private PdfSignatureDetectionService $service;

	public function setUp(): void {
		parent::setUp();

		$signEngineFactory = \OCP\Server::get(SignEngineFactory::class);
		$logger = \OCP\Server::get(LoggerInterface::class);

		$this->service = new PdfSignatureDetectionService(
			$signEngineFactory,
			$logger
		);
	}

	public static function pdfContentProvider(): array {
		$catalog = new PdfFixtureCatalog();

		$signedFixture = $catalog->getByFilename('small_valid-signed.pdf');
		$signedPdf = $signedFixture ? file_get_contents($signedFixture->getFilePath()) : '';

		$unsignedFixture = $catalog->getByFilename('small_valid.pdf');
		$unsignedPdf = $unsignedFixture ? file_get_contents($unsignedFixture->getFilePath()) : '';


		return [
			'signed PDF from catalog' => [fn () => $signedPdf, true],
			'unsigned PDF from catalog' => [fn () => $unsignedPdf, false],
			'synthetic PDF with DocMDP level 1' => [fn () => PdfGenerator::createPdfWithDocMdp(1), false],
			'synthetic PDF with DocMDP level 2' => [fn () => PdfGenerator::createPdfWithDocMdp(2), false],
			'synthetic PDF with DocMDP level 3' => [fn () => PdfGenerator::createPdfWithDocMdp(3), false],
			'synthetic minimal PDF unsigned' => [fn () => PdfGenerator::createMinimalPdf(), false],
			'empty string' => [fn () => '', false],
			'invalid content' => [fn () => 'not a valid pdf content', false],
		];
	}

	#[DataProvider('pdfContentProvider')]
	public function testHasSignatures(callable $pdfProvider, bool $expected): void {
		$result = $this->service->hasSignatures($pdfProvider());
		$this->assertSame($expected, $result);
	}
}
