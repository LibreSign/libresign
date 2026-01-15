<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\File\Pdf;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Service\File\Pdf\PdfParser;
use OCA\Libresign\Service\File\Pdf\PdfValidator;
use OCA\Libresign\Tests\Fixtures\PdfGenerator;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;

final class PdfValidatorTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private PdfParser|MockObject $pdfParser;
	private DocMdpHandler|MockObject $docMdp;
	private IL10N|MockObject $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->pdfParser = $this->createMock(PdfParser::class);
		$this->docMdp = $this->createMock(DocMdpHandler::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(fn ($text, array $params = []) => vsprintf($text, $params));
	}

	private function getService(): PdfValidator {
		return new PdfValidator(
			$this->pdfParser,
			$this->docMdp,
			$this->l10n,
		);
	}

	public function testValidateSucceedsWhenPdfIsValidAndDocMdpAllows(): void {
		$content = PdfGenerator::createCompletePdfStructure(2);

		$this->pdfParser
			->expects($this->once())
			->method('parse')
			->with($content, 'file.pdf');

		$this->docMdp
			->expects($this->once())
			->method('allowsAdditionalSignatures')
			->willReturn(true);

		$validator = $this->getService();
		$validator->validate($content, 'file.pdf');
	}

	public function testValidateThrowsWhenDocMdpDisallows(): void {
		$content = PdfGenerator::createCompletePdfStructure(1);

		$this->pdfParser->method('parse');
		$this->docMdp->method('allowsAdditionalSignatures')->willReturn(false);

		$validator = $this->getService();

		$this->expectException(LibresignException::class);
		$this->expectExceptionMessage('doc.pdf');
		$validator->validate($content, 'doc.pdf');
	}
}
