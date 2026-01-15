<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File\Pdf;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\File\Pdf\PdfMetadataExtractor;
use OCA\Libresign\Service\File\Pdf\PdfParser;
use OCP\Files\File;
use OCP\ITempManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class PdfMetadataExtractorTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private PdfParser|MockObject $pdfParser;
	private ITempManager|MockObject $tempManager;
	private LoggerInterface|MockObject $logger;

	public function setUp(): void {
		parent::setUp();
		$this->pdfParser = $this->createMock(PdfParser::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function getService(): PdfMetadataExtractor {
		return new PdfMetadataExtractor(
			$this->pdfParser,
			$this->tempManager,
			$this->logger,
		);
	}

	public function testSetFileThrowsOnEmptyContent(): void {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('empty.pdf');
		$file->method('getContent')->willReturn('');

		$service = $this->getService();

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('empty.pdf');
		$service->setFile($file);
	}

	public function testSetFileThrowsOnReadError(): void {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('unreadable.pdf');
		$file->method('getContent')->willThrowException(new \Exception('Permission denied'));

		$service = $this->getService();

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('Unable to read file "unreadable.pdf": Permission denied');
		$service->setFile($file);
	}

	#[DataProvider('pdfVersionProvider')]
	public function testGetPdfVersion(string $content, string $expectedVersion): void {
		$file = $this->createMock(File::class);
		$file->method('getContent')->willReturn($content);
		$file->method('getName')->willReturn('test.pdf');

		$service = $this->getService();
		$service->setFile($file);

		$version = $service->getPdfVersion();

		$this->assertEquals($expectedVersion, $version);
	}

	public static function pdfVersionProvider(): array {
		return [
			'PDF 1.4' => ['%PDF-1.4', '1.4'],
			'PDF 1.5 with content' => ['%PDF-1.5\nsome content', '1.5'],
			'PDF 1.7' => ["%PDF-1.7\n%test content", '1.7'],
			'PDF 2.0' => ['%PDF-2.0', '2.0'],
		];
	}

	public function testGetPageDimensionsThrowsWhenFileNotSet(): void {
		$service = $this->getService();

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('File not defined to be parsed');
		$service->getPageDimensions();
	}
}
