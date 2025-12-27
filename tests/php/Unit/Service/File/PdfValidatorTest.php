<?php

declare(strict_types=1);

namespace OCA\Libresign\Tests\Unit\Service\File;

use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Service\File\PdfValidator;
use OCA\Libresign\Tests\Fixtures\PdfGenerator;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class PdfValidatorTest extends \OCA\Libresign\Tests\Unit\TestCase {
	private DocMdpHandler|MockObject $docMdp;
	private LoggerInterface|MockObject $logger;
	private IL10N|MockObject $l10n;

	public function setUp(): void {
		parent::setUp();
		$this->docMdp = $this->createMock(DocMdpHandler::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(fn ($text) => $text);
	}

	private function getService(): PdfValidator {
		return new PdfValidator(
			$this->docMdp,
			$this->logger,
			$this->l10n,
		);
	}

	public function testValidateAllowsWhenDocMdpAllows(): void {
		$content = PdfGenerator::createCompletePdfStructure(2);

		$this->docMdp->method('allowsAdditionalSignatures')->willReturn(true);

		$validator = $this->getService();

		$this->expectNotToPerformAssertions();
		$validator->validate($content);
	}

	public function testValidateThrowsOnInvalidPdf(): void {
		$validator = $this->getService();

		$this->expectException(\Exception::class);
		$validator->validate('not a pdf');
	}

	public function testValidateThrowsLibresignWhenDocMdpDisallows(): void {
		$content = PdfGenerator::createCompletePdfStructure(1);

		$this->docMdp->method('allowsAdditionalSignatures')->willReturn(false);

		$validator = $this->getService();

		$this->expectException(LibresignException::class);
		$validator->validate($content);
	}
}
