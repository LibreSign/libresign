<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File\Pdf;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Service\File\Pdf\PdfParser;
use OCA\Libresign\Tests\Fixtures\PdfGenerator;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class PdfParserTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private LoggerInterface|MockObject $logger;
	private IL10N|MockObject $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(fn ($text, array $params = []) => vsprintf($text, $params));
	}

	private function getService(): PdfParser {
		return new PdfParser(
			$this->logger,
			$this->l10n,
		);
	}

	public function testParseValidPdfReturnsDocument(): void {
		$content = PdfGenerator::createCompletePdfStructure(2);

		$parser = $this->getService();
		$document = $parser->parse($content, 'test.pdf');

		$this->assertInstanceOf(\OCA\Libresign\Vendor\Smalot\PdfParser\Document::class, $document);
	}

	public function testParseInvalidPdfThrowsExceptionWithFilename(): void {
		$parser = $this->getService();

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('invalid.pdf');
		$parser->parse('not a pdf', 'invalid.pdf');
	}
}
