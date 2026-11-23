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
use OCA\Libresign\Tests\Fixtures\PdfGenerator;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DocMdpHandlerTest extends TestCase {
	private IL10N&MockObject $l10n;
	private DocMdpHandler $handler;

	protected function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->handler = new DocMdpHandler($this->l10n);
	}

	public function testUnsignedPdfIsDetectedAsLevelNone(): void {
		$pdfContent = PdfGenerator::createMinimalPdf();

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level']);
	}

	public function testP0AllowsAnyModification(): void {
		$pdfContent = PdfGenerator::createPdfWithDocMdp(0, withModifications: true);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid']);
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP1ProhibitsAnyModification(): void {
		$pdfContent = PdfGenerator::createPdfWithDocMdp(1, withModifications: true);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertFalse($result['modification_validation']['valid']);
		$this->assertSame(File::MODIFICATION_VIOLATION, $result['modification_validation']['status']);
	}

	public function testP2AllowsFormFieldModifications(): void {
		$pdfContent = PdfGenerator::createPdfWithFormFieldModification(2);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=2 MUST allow form field modifications per ISO 32000-1');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP2ProhibitsAnnotationModifications(): void {
		$pdfContent = PdfGenerator::createPdfWithAnnotationModification(2);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertFalse($result['modification_validation']['valid'], 'P=2 MUST reject annotations per ISO 32000-1');
		$this->assertSame(File::MODIFICATION_VIOLATION, $result['modification_validation']['status']);
	}

	public function testP3AllowsFormFieldModifications(): void {
		$pdfContent = PdfGenerator::createPdfWithFormFieldModification(3);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=3 MUST allow form fields per ISO 32000-1');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP3AllowsAnnotationModifications(): void {
		$pdfContent = PdfGenerator::createPdfWithAnnotationModification(3);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=3 MUST allow annotations per ISO 32000-1');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP3ProhibitsStructuralModifications(): void {
		$pdfContent = PdfGenerator::createPdfWithStructuralModification(3);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertFalse($result['modification_validation']['valid'], 'P=3 MUST reject structural changes per ISO 32000-1');
		$this->assertSame(File::MODIFICATION_VIOLATION, $result['modification_validation']['status']);
	}

	public function testP2AllowsSubsequentSignatures(): void {
		$pdfContent = PdfGenerator::createPdfWithSubsequentSignature(2);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=2 MUST allow subsequent signatures per ISO 32000-1 Section 12.8.2.2');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP3AllowsSubsequentSignatures(): void {
		$pdfContent = PdfGenerator::createPdfWithSubsequentSignature(3);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=3 MUST allow subsequent signatures per ISO 32000-1 Section 12.8.2.2');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP1ProhibitsSubsequentSignatures(): void {
		$pdfContent = PdfGenerator::createPdfWithSubsequentSignature(1);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertFalse($result['modification_validation']['valid'], 'P=1 MUST prohibit subsequent signatures per ISO 32000-1 Section 12.8.2.2');
		$this->assertSame(File::MODIFICATION_VIOLATION, $result['modification_validation']['status']);
	}

	public function testExtractsDocMdpFromSignatureReferenceNotPerms(): void {
		$pdfContent = PdfGenerator::createPdfWithDocMdpInSignatureReference(2);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING->value, $result['docmdp']['level'], 'Must extract DocMDP from /Reference per ICP-Brasil recommendation (not /Perms)');
	}

	public function testExtractsDocMdpFromFirstCertifyingSignature(): void {
		$pdfContent = PdfGenerator::createPdfWithApprovalThenCertifyingSignature();

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED->value, $result['docmdp']['level'], 'Must extract DocMDP from first CERTIFYING signature, not first signature in file');
	}

	public function testP2AllowsPageTemplateInstantiation(): void {
		$pdfContent = PdfGenerator::createPdfWithPageTemplate(2);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=2 MUST allow page template instantiation per ISO 32000-1 Table 254');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testP3AllowsPageTemplateInstantiation(): void {
		$pdfContent = PdfGenerator::createPdfWithPageTemplate(3);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertTrue($result['modification_validation']['valid'], 'P=3 MUST allow page template instantiation per ISO 32000-1 Table 254');
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public function testExtractsDocMdpWithIndirectReferenceItiStyle(): void {
		$pdfContent = PdfGenerator::createPdfWithIndirectReferencesItiStyle(2);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING->value, $result['docmdp']['level'], 'Must extract DocMDP from indirect references per ICP-Brasil example');
	}

	public function testValidatesTransformParamsVersion(): void {
		$pdfContent = PdfGenerator::createPdfWithDocMdp(2);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING->value, $result['docmdp']['level'], 'Must accept /V /1.2 in TransformParams per ICP-Brasil');
	}

	public function testRejectsDocMdpWithoutVersion(): void {
		$pdfContent = PdfGenerator::createPdfWithDocMdpWithoutVersion(2);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'Must reject DocMDP without /V version per ICP-Brasil requirement');
	}

	public function testRejectsDocMdpWithInvalidVersion(): void {
		$pdfContent = PdfGenerator::createPdfWithDocMdpInvalidVersion(2, '1.0');

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'Must reject DocMDP with /V 1.0 (only /V /1.2 allowed per ICP-Brasil)');
	}

	public function testRejectsDocMdpWithInvalidVersionIndirectRef(): void {
		$pdfContent = PdfGenerator::createPdfWithIndirectReferencesInvalidVersion(2, '1.3');

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
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
		$pdfContent = PdfGenerator::createPdfWithDocMdp($pValue);

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame($expectedLevel->value, $result['docmdp']['level'], "PDF with P=$pValue must be detected as {$expectedLevel->name}");
	}

	// PDF fixture methods are now provided by PdfFixtureTrait

	// ISO 32000-1 Table 252 validation tests
	public function testRejectsSignatureDictionaryWithoutTypeWhenPresent(): void {
		$pdf = PdfGenerator::createPdfWithInvalidSignatureType();

		$resource = PdfGenerator::createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1 Table 252: if /Type present in signature dict, must be /Sig');
	}

	public function testRejectsSignatureWithoutFilterEntry(): void {
		$pdf = PdfGenerator::createPdfWithoutFilterEntry();

		$resource = PdfGenerator::createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1 Table 252: /Filter is Required in signature dictionary');
	}

	public function testRejectsSignatureWithoutByteRange(): void {
		$pdf = PdfGenerator::createPdfWithoutByteRange();

		$resource = PdfGenerator::createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1: ByteRange required when DocMDP transform method is used');
	}

	public function testPreservesDocMdpLevelWithMultipleSignatures(): void {
		$pdf = PdfGenerator::createPdfWithMultipleDocMdpSignatures();

		$resource = PdfGenerator::createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::CERTIFIED_FORM_FILLING->value, $result['docmdp']['level'], 'ISO 32000-1 12.8.2.2: Must preserve DocMDP level from first certifying signature');
	}

	public function testWarnsWhenMultipleDocMdpSignaturesViolateIso(): void {
		$pdf = PdfGenerator::createPdfWithMultipleDocMdpSignatures();

		$resource = PdfGenerator::createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertArrayHasKey('docmdp_validation', $result, 'ISO 32000-1 12.8.2.2.1: Must warn when multiple DocMDP signatures found (only first shall be certifying)');
		$this->assertFalse($result['docmdp_validation']['valid']);
	}

	public function testRejectsDocMdpNotFirstSignature(): void {
		$pdf = PdfGenerator::createPdfWithDocMdpNotFirst();

		$resource = PdfGenerator::createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1 12.8.2.2.1: DocMDP signature shall be the first signed field in the document');
	}

	public function testRejectsSigRefWithoutTransformMethod(): void {
		$pdf = PdfGenerator::createPdfWithSigRefWithoutTransformMethod();

		$resource = PdfGenerator::createResourceFromContent($pdf);
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
			$pdfContent = PdfGenerator::createMinimalPdf();
		} else {
			// PDF with DocMDP signature at specified level (0, 1, 2, or 3)
			$pdfContent = PdfGenerator::createPdfWithDocMdp($level, $withModifications);
		}

		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->allowsAdditionalSignatures($resource);
		fclose($resource);

		$this->assertSame($expectedAllowed, $result);
	}

	/** @return array<int, string> */
	private static function dssUpdate(): array {
		return [
			10 => "<< /Length 4 >>\nstream\n0000\nendstream",
			11 => '<< /Type /DSS /Certs [10 0 R] /OCSPs [] /CRLs [] >>',
			// Same catalog rewritten by another writer, plus /DSS, /Extensions and /Version.
			1 => '<</Type/Catalog/Pages 2 0 R/AcroForm 4 0 R/Perms<</DocMDP 5 0 R>>/DSS 11 0 R'
				. '/Extensions<</ADBE<</BaseVersion/1.7/ExtensionLevel 5>>>>/Version/1.7>>',
			// Information dictionary rewritten: strings re-encoded, new /ModDate and /Producer.
			7 => '<< /Producer (Generator\073 LTV) /ModDate <443a32303236303230323030303030305a> >>',
		];
	}

	/** @return array<int, string> */
	private static function timestampUpdate(string $field = '/FT /Sig /T (Timestamp1) /Type /Annot /Subtype /Widget /F 132 /Rect [0 0 0 0] /P 3 0 R /V 12 0 R'): array {
		return [
			12 => '<< /Type /DocTimeStamp /Filter /Adobe.PPKLite /SubFilter /ETSI.RFC3161 /ByteRange [0000000000 0000000000 0000000000 0000000000] /Contents <00> >>',
			13 => "<< {$field} >>",
			3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Annots [13 0 R] >>',
			4 => '<< /SigFlags 3 /Fields [6 0 R 13 0 R] >>',
		];
	}

	public static function validationDataUpdateProvider(): array {
		return [
			'P=1 DSS' => [1, [self::dssUpdate()]],
			'P=2 DSS' => [2, [self::dssUpdate()]],
			'P=3 DSS' => [3, [self::dssUpdate()]],
			'P=1 document timestamp' => [1, [self::timestampUpdate()]],
			'P=1 DSS and document timestamp in one update' => [1, [self::dssUpdate() + self::timestampUpdate()]],
			'P=1 document timestamp, then DSS' => [1, [self::timestampUpdate(), self::dssUpdate()]],
		];
	}

	/**
	 * ISO 32000-2 12.8.2.2.1 / ETSI EN 319 142-1 5.4: adding a DSS or a document
	 * timestamp after certification is allowed at every DocMDP level.
	 */
	#[DataProvider('validationDataUpdateProvider')]
	public function testValidationDataUpdateAfterCertificationIsAllowed(int $pValue, array $updates): void {
		$result = $this->extractFromUpdates($pValue, $updates);

		$this->assertSame($pValue, $result['docmdp']['level']);
		$this->assertTrue($result['modifications']['modified']);
		$this->assertTrue($result['modification_validation']['valid']);
		$this->assertSame(File::MODIFICATION_ALLOWED, $result['modification_validation']['status']);
	}

	public static function contentChangeWithValidationDataProvider(): array {
		$timestampField = '/FT /Sig /T (Timestamp1) /Type /Annot /Subtype /Widget /F 132 /Rect [0 0 0 0] /P 3 0 R /V 12 0 R';
		return [
			'page content added' => [[self::dssUpdate() + [
				20 => "<< /Length 18 >>\nstream\nBT (changed) Tj ET\nendstream",
				3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 20 0 R >>',
			]]],
			'catalog action added' => [[[
				10 => '<< /Type /DSS >>',
				1 => '<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R /Perms << /DocMDP 5 0 R >> /DSS 10 0 R /OpenAction 3 0 R >>',
			]]],
			'duplicate catalog key' => [[[
				10 => '<< /Type /DSS >>',
				1 => '<< /Type /Catalog /Pages 2 0 R /Pages 20 0 R /AcroForm 4 0 R /Perms << /DocMDP 5 0 R >> /DSS 10 0 R >>',
			]]],
			'information dictionary title changed' => [[[7 => '<< /Producer (Generator) /ModDate (D:20260101000000Z) /Title (Changed) >>']]],
			'pages tree rewritten' => [[[2 => '<< /Type /Pages /Kids [3 0 R 20 0 R] /Count 2 >>', 20 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>']]],
			'text annotation added' => [[self::dssUpdate() + [
				20 => '<< /Type /Annot /Subtype /Text /Rect [100 100 200 200] >>',
				3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Annots [20 0 R] >>',
			]]],
			'non-signature field appended' => [[self::dssUpdate() + [
				20 => '<< /FT /Tx /T (Text1) /V (changed) >>',
				4 => '<< /SigFlags 3 /Fields [6 0 R 20 0 R] >>',
			]]],
			'existing field removed' => [[array_replace(self::timestampUpdate(), [4 => '<< /SigFlags 3 /Fields [13 0 R] >>'])]],
			'approval signature added' => [[self::dssUpdate() + [
				20 => '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached /ByteRange [0 10 20 10] /Contents <00> >>',
				21 => '<< /FT /Sig /T (Signature2) /V 20 0 R >>',
				4 => '<< /SigFlags 3 /Fields [6 0 R 21 0 R] >>',
			]]],
			'signature field value is not a document timestamp' => [[array_replace(self::timestampUpdate(), [
				12 => '<< /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached /ByteRange [0 10 20 10] /Contents <00> >>',
			])]],
			'visible timestamp widget' => [[self::timestampUpdate(str_replace('[0 0 0 0]', '[0 0 612 792]', $timestampField))]],
			'timestamp field with an action' => [[self::timestampUpdate($timestampField . ' /AA << /F << /S /JavaScript /JS (x) >> >>')]],
			'timestamp field with kids' => [[self::timestampUpdate($timestampField . ' /Kids [3 0 R]')]],
			'object stream' => [[self::dssUpdate() + [20 => "<< /Type /ObjStm /N 1 /First 5 /Length 8 >>\nstream\n3 0 <<>>\nendstream"]]],
			'object stream behind a name escape' => [[self::dssUpdate() + [20 => "<< /Type /Obj#53tm /N 1 /First 5 /Length 8 >>\nstream\n3 0 <<>>\nendstream"]]],
		];
	}

	#[DataProvider('contentChangeWithValidationDataProvider')]
	public function testP1RejectsContentChangeAlongsideValidationData(array $updates): void {
		$result = $this->extractFromUpdates(1, $updates);

		$this->assertFalse($result['modification_validation']['valid']);
		$this->assertSame(File::MODIFICATION_VIOLATION, $result['modification_validation']['status']);
	}

	public static function forgedTrailerProvider(): array {
		return [
			'root replaced' => [['/Root' => '20 0 R']],
			'information dictionary replaced' => [['/Info' => '20 0 R']],
			'encryption added' => [['/Encrypt' => '20 0 R']],
			'previous section skipped' => [['/Prev' => '0']],
		];
	}

	#[DataProvider('forgedTrailerProvider')]
	public function testP1RejectsValidationDataUpdateWithForgedTrailer(array $trailerOverrides): void {
		$update = self::dssUpdate() + [20 => '<< /Type /Catalog /Pages 2 0 R >>'];
		$result = $this->extractFromUpdates(1, [$update], $trailerOverrides);

		$this->assertFalse($result['modification_validation']['valid']);
	}

	public function testP1RejectsObjectsOutsideTheUpdateXref(): void {
		$pdf = PdfGenerator::createCertifiedPdfWithIncrementalUpdates(1, [self::dssUpdate()]);
		// An object the xref does not list still reaches readers that rebuild the xref.
		$pos = strrpos($pdf, "xref\n");
		$pdf = substr($pdf, 0, $pos) . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n" . substr($pdf, $pos);

		$result = $this->extractFromContent($pdf);

		$this->assertFalse($result['modification_validation']['valid']);
	}

	public function testP1RejectsValidationDataInXrefStreamUpdate(): void {
		$pdf = PdfGenerator::createCertifiedPdfWithIncrementalUpdates(1, [self::dssUpdate()]);
		$pdf = substr($pdf, 0, strrpos($pdf, "xref\n"))
			. "30 0 obj\n<< /Type /XRef /Size 31 /Root 1 0 R /W [1 4 1] /Length 0 >>\nstream\n\nendstream\nendobj\n"
			. "startxref\n" . strlen($pdf) . "\n%%EOF\n";

		$result = $this->extractFromContent($pdf);

		$this->assertFalse($result['modification_validation']['valid']);
	}

	private function extractFromUpdates(int $pValue, array $updates, array $trailerOverrides = []): array {
		return $this->extractFromContent(PdfGenerator::createCertifiedPdfWithIncrementalUpdates($pValue, $updates, $trailerOverrides));
	}

	private function extractFromContent(string $pdfContent): array {
		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);
		return $result;
	}

	public static function standaloneDocMdpSignatureProvider(): array {
		return [
			'Unreferenced P=1 dictionary is not a certification' => ['', [], true],
			'Unreferenced P=1 dictionary next to an empty signature field is not a certification' => ['/AcroForm << /Fields [6 0 R] >>', [6 => '<< /FT /Sig /T (Signature1) >>'], true],
			'P=1 dictionary referenced from /Perms still blocks' => ['/Perms << /DocMDP 5 0 R >>', [], false],
			'P=1 dictionary referenced from a signature field still blocks' => ['/AcroForm << /Fields [6 0 R] >>', [6 => '<< /FT /Sig /T (Signature1) /V 5 0 R >>'], false],
		];
	}

	#[DataProvider('standaloneDocMdpSignatureProvider')]
	public function testOnlyReferencedSignatureDictionariesCertify(string $catalogExtra, array $extraObjects, bool $expectedAllowed): void {
		$pdfContent = PdfGenerator::createPdfWithStandaloneDocMdpSignature(1, $catalogExtra, $extraObjects);
		$resource = PdfGenerator::createResourceFromContent($pdfContent);
		$allowed = $this->handler->allowsAdditionalSignatures($resource);
		$data = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame($expectedAllowed, $allowed);
		$expectedLevel = $expectedAllowed ? DocMdpLevel::NOT_CERTIFIED : DocMdpLevel::CERTIFIED_NO_CHANGES_ALLOWED;
		$this->assertSame($expectedLevel->value, $data['docmdp']['level']);
	}

	public function testRealJSignPdfWithDocMdpLevel1(): void {
		$pdfPath = __DIR__ . '/../../fixtures/pdfs/real_jsignpdf_level1.pdf';

		if (!file_exists($pdfPath)) {
			$this->markTestSkipped('Real JSignPdf test PDF not found');
		}

		$content = file_get_contents($pdfPath);
		$resource = PdfGenerator::createResourceFromContent($content);

		$data = $this->handler->extractDocMdpData($resource);

		rewind($resource);
		$allows = $this->handler->allowsAdditionalSignatures($resource);

		fclose($resource);

		$this->assertSame(1, $data['docmdp']['level'], 'Should detect DocMDP level 1 from real JSignPdf');
		$this->assertFalse($allows, 'Should not allow additional signatures for DocMDP level 1');
	}
}
