<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Handler;

use OCA\Libresign\Db\File;
use OCA\Libresign\Enum\DocMdpLevel;
use OCA\Libresign\Handler\DocMdpHandler;
use OCA\Libresign\Tests\Unit\PdfFixtureTrait;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DocMdpHandlerTest extends TestCase {
	use PdfFixtureTrait;
	private IL10N&MockObject $l10n;
	private DocMdpHandler $handler;

	protected function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->handler = new DocMdpHandler($this->l10n);
	}

	public function testUnsignedPdfIsDetectedAsLevelNone(): void {
		$pdfContent = $this->createMinimalPdf();

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level']);
	}

	public function testP0AllowsAnyModification(): void {
		$pdfContent = $this->createPdfWithDocMdp(0, withModifications: true);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid']);
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP1ProhibitsAnyModification(): void {
		$pdfContent = $this->createPdfWithDocMdp(1, withModifications: true);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertFalse($result['modification_validation']['valid']);
		$this->assertSame(File::MODIFICATION_VIOLATION, $result['modification_validation']['status']);
	}

	public function testP2AllowsFormFieldModifications(): void {
		$pdfContent = $this->createPdfWithFormFieldModification(2);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=2 MUST allow form field modifications per ISO 32000-1');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP2ProhibitsAnnotationModifications(): void {
		$pdfContent = $this->createPdfWithAnnotationModification(2);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertFalse($result['modification_validation']['valid'], 'P=2 MUST reject annotations per ISO 32000-1');
		$this->assertSame(File::MODIFICATION_VIOLATION, $result['modification_validation']['status']);
	}

	public function testP3AllowsFormFieldModifications(): void {
		$pdfContent = $this->createPdfWithFormFieldModification(3);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=3 MUST allow form fields per ISO 32000-1');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP3AllowsAnnotationModifications(): void {
		$pdfContent = $this->createPdfWithAnnotationModification(3);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=3 MUST allow annotations per ISO 32000-1');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP3ProhibitsStructuralModifications(): void {
		$pdfContent = $this->createPdfWithStructuralModification(3);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertFalse($result['modification_validation']['valid'], 'P=3 MUST reject structural changes per ISO 32000-1');
		$this->assertSame(File::MODIFICATION_VIOLATION, $result['modification_validation']['status']);
	}

	public function testP2AllowsSubsequentSignatures(): void {
		$pdfContent = $this->createPdfWithSubsequentSignature(2);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=2 MUST allow subsequent signatures per ISO 32000-1 Section 12.8.2.2');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP3AllowsSubsequentSignatures(): void {
		$pdfContent = $this->createPdfWithSubsequentSignature(3);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=3 MUST allow subsequent signatures per ISO 32000-1 Section 12.8.2.2');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP1ProhibitsSubsequentSignatures(): void {
		$pdfContent = $this->createPdfWithSubsequentSignature(1);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertFalse($result['modification_validation']['valid'], 'P=1 MUST prohibit subsequent signatures per ISO 32000-1 Section 12.8.2.2');
		$this->assertSame(File::MODIFICATION_VIOLATION, $result['modification_validation']['status']);
	}

	public function testExtractsDocMdpFromSignatureReferenceNotPerms(): void {
		$pdfContent = $this->createPdfWithDocMdpInSignatureReference(2);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING->value, $result['docmdp']['level'], 'Must extract DocMDP from /Reference per ICP-Brasil recommendation (not /Perms)');
	}

	public function testExtractsDocMdpFromFirstCertifyingSignature(): void {
		$pdfContent = $this->createPdfWithApprovalThenCertifyingSignature();

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value, $result['docmdp']['level'], 'Must extract DocMDP from first CERTIFYING signature, not first signature in file');
	}

	public function testP2AllowsPageTemplateInstantiation(): void {
		$pdfContent = $this->createPdfWithPageTemplate(2);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=2 MUST allow page template instantiation per ISO 32000-1 Table 254');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP3AllowsPageTemplateInstantiation(): void {
		$pdfContent = $this->createPdfWithPageTemplate(3);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=3 MUST allow page template instantiation per ISO 32000-1 Table 254');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testExtractsDocMdpWithIndirectReferenceItiStyle(): void {
		$pdfContent = $this->createPdfWithIndirectReferencesItiStyle(2);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING->value, $result['docmdp']['level'], 'Must extract DocMDP from indirect references per ICP-Brasil example');
	}

	public function testValidatesTransformParamsVersion(): void {
		$pdfContent = $this->createPdfWithDocMdpVersion12(2);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING->value, $result['docmdp']['level'], 'Must accept /V /1.2 in TransformParams per ICP-Brasil');
	}

	public function testRejectsDocMdpWithoutVersion(): void {
		$pdfContent = $this->createPdfWithDocMdpWithoutVersion(2);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'Must reject DocMDP without /V version per ICP-Brasil requirement');
	}

	public function testRejectsDocMdpWithInvalidVersion(): void {
		$pdfContent = $this->createPdfWithDocMdpInvalidVersion(2, '1.0');

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'Must reject DocMDP with /V 1.0 (only /V /1.2 allowed per ICP-Brasil)');
	}

	public function testRejectsDocMdpWithInvalidVersionIndirectRef(): void {
		$pdfContent = $this->createPdfWithIndirectReferencesInvalidVersion(2, '1.3');

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'Must reject indirect DocMDP with /V 1.3 (only /V /1.2 allowed)');
	}

	public static function docMdpLevelExtractionProvider(): array {
		return [
			'Level P=0 NOT_CERTIFIED' => [0, DocMdpLevel::NOT_CERTIFIED],
			'Level P=1 CERTIFIED_NO_CHANGES_ALLOWED' => [1, DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED],
			'Level P=2 CERTIFIED_FORM_FILLING' => [2, DocMdpLevel::CERTIFIED_FORM_FILLING],
			'Level P=3 CERTIFIED_FORM_FILLING_AND_ANNOTATIONS' => [3, DocMdpLevel::CERTIFIED_FORM_FILLING_AND_ANNOTATIONS],
		];
	}

	#[DataProvider('docMdpLevelExtractionProvider')]
	public function testExtractsDocMdpPermissionLevel(int $pValue, DocMdpLevel $expectedLevel): void {
		$pdfContent = $this->createPdfWithDocMdp($pValue);

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame($expectedLevel->value, $result['docmdp']['level'], "PDF with P=$pValue must be detected as {$expectedLevel->name}");
	}


	// PDF fixture methods are now provided by PdfFixtureTrait

	// ISO 32000-1 Table 252 validation tests
	public function testRejectsSignatureDictionaryWithoutTypeWhenPresent(): void {
		$pdf = $this->createPdfWithInvalidSignatureType();

		$resource = $this->createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1 Table 252: if /Type present in signature dict, must be /Sig');
	}

	public function testRejectsSignatureWithoutFilterEntry(): void {
		$pdf = $this->createPdfWithoutFilterEntry();

		$resource = $this->createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1 Table 252: /Filter is Required in signature dictionary');
	}

	public function testRejectsSignatureWithoutByteRange(): void {
		$pdf = $this->createPdfWithoutByteRange();

		$resource = $this->createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1: ByteRange required when DocMDP transform method is used');
	}

	public function testRejectsMultipleDocMdpSignatures(): void {
		$pdf = $this->createPdfWithMultipleDocMdpSignatures();

		$resource = $this->createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1 12.8.2.2.1: A document can contain only one signature field that contains a DocMDP transform method');
	}

	public function testRejectsDocMdpNotFirstSignature(): void {
		$pdf = $this->createPdfWithDocMdpNotFirst();

		$resource = $this->createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1 12.8.2.2.1: DocMDP signature shall be the first signed field in the document');
	}

	public function testRejectsSigRefWithoutTransformMethod(): void {
		$pdf = $this->createPdfWithSigRefWithoutTransformMethod();

		$resource = $this->createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1 Table 253: /TransformMethod is Required in signature reference dictionary');
	}

	public static function additionalSignaturesProvider(): array {
		return [
			// PDFs without any signature
			'Unsigned PDF (virgin) - allows signatures' => ['unsigned', false, true],

			// PDFs with DocMDP signature, no modifications
			'DocMDP P=0 (no restrictions, unmodified) - allows additional signatures' => [0, false, true],
			'DocMDP P=1 (no changes allowed, unmodified) - prohibits additional signatures' => [1, false, false],
			'DocMDP P=2 (form filling allowed, unmodified) - allows additional signatures' => [2, false, true],
			'DocMDP P=3 (form+annotations allowed, unmodified) - allows additional signatures' => [3, false, true],

			// PDFs with DocMDP signature, with modifications
			'DocMDP P=0 (no restrictions, modified) - allows additional signatures' => [0, true, true],
			'DocMDP P=1 (no changes allowed, modified) - prohibits additional signatures' => [1, true, false],
			'DocMDP P=2 (form filling allowed, modified) - allows additional signatures' => [2, true, true],
			'DocMDP P=3 (form+annotations allowed, modified) - allows additional signatures' => [3, true, true],
		];
	}

	#[DataProvider('additionalSignaturesProvider')]
	public function testAdditionalSignaturesBasedOnDocMdpLevel(string|int $level, bool $withModifications, bool $expectedAllowed): void {
		if ($level === 'unsigned') {
			// PDF without any signature (virgin PDF)
			$pdfContent = $this->createMinimalPdf();
		} else {
			// PDF with DocMDP signature at specified level (0, 1, 2, or 3)
			$pdfContent = $this->createPdfWithDocMdp($level, $withModifications);
		}

		$resource = $this->createResourceFromContent($pdfContent);
		$result = $this->handler->allowsAdditionalSignatures($resource);
		fclose($resource);

		$this->assertSame($expectedAllowed, $result);
	}

	public function testRealJSignPdfWithDocMdpLevel1(): void {
		$pdfPath = __DIR__ . '/../../fixtures/real_jsignpdf_level1.pdf';

		if (!file_exists($pdfPath)) {
			$this->markTestSkipped('Real JSignPdf test PDF not found');
		}

		$content = file_get_contents($pdfPath);
		$resource = $this->createResourceFromContent($content);

		$data = $this->handler->extractDocMdpData($resource);

		rewind($resource);
		$allows = $this->handler->allowsAdditionalSignatures($resource);

		fclose($resource);

		$this->assertSame(1, $data['docmdp']['level'], 'Should detect DocMDP level 1 from real JSignPdf');
		$this->assertFalse($allows, 'Should not allow additional signatures for DocMDP level 1');
	}
}
