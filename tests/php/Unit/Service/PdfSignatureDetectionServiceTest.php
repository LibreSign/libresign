<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service;

use OCA\Libresign\Handler\SignEngine\SignEngineFactory;
use OCA\Libresign\Service\PdfSignatureDetectionService;
use OCA\Libresign\Tests\Unit\PdfFixtureTrait;
use OCA\Libresign\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

class PdfSignatureDetectionServiceTest extends TestCase {
	use PdfFixtureTrait;

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
		$fixture = new class { use PdfFixtureTrait; };

		return [
			'signed PDF with DocMDP level 1' => [fn() => $fixture->createPdfWithDocMdp(1), true],
			'signed PDF with DocMDP level 2' => [fn() => $fixture->createPdfWithDocMdp(2), true],
			'signed PDF with DocMDP level 3' => [fn() => $fixture->createPdfWithDocMdp(3), true],
			'unsigned minimal PDF' => [fn() => $fixture->createMinimalPdf(), false],
			'empty string' => [fn() => '', false],
			'invalid content' => [fn() => 'not a valid pdf content', false],
		];
	}

	#[DataProvider('pdfContentProvider')]
	public function testHasSignatures(callable $pdfProvider, bool $expected): void {
		$result = $this->service->hasSignatures($pdfProvider());
		$this->assertSame($expected, $result);
	}
}
