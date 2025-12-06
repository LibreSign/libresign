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

	private function createResourceFromContent(string $content) {
		$resource = tmpfile();
		fwrite($resource, $content);
		fseek($resource, 0);
		return $resource;
	}

	private function createMinimalPdf(): string {
		return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
			. "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
			. "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
			. "xref\n0 4\ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n190\n%%EOF";
	}

	private function createPdfWithDocMdp(int $pValue, bool $withModifications = false): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R /Perms << /DocMDP 5 0 R >> >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";

		$signatureStart = strlen($pdf) + 200;
		$signatureLength = 100;
		$offset1 = 0;
		$length1 = $signatureStart;
		$offset2 = $signatureStart + $signatureLength;

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$pdf .= "/Reference [ << /Type /SigRef /TransformMethod /DocMDP\n";
		$pdf .= "/TransformParams << /Type /TransformParams /P $pValue /V /1.2 >> >> ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		if ($withModifications) {
			$targetLength = $offset2 + $length2;
			while (strlen($pdf) < $targetLength) {
				$pdf .= ' ';
			}

			$pdf .= "\n7 0 obj\n<< /Type /Annot /Subtype /Text /Rect [100 100 200 200] >>\nendobj\n";
			$pdf .= "xref\n7 1\ntrailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n" . strlen($pdf) . "\n%%EOF";
		} else {
			$startxref = strlen($pdf);
			$pdf .= "xref\n0 7\ntrailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n$startxref\n%%EOF";
		}

		return $pdf;
	}

	private function createPdfWithFormFieldModification(int $pValue): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R /Perms << /DocMDP 5 0 R >> >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R 7 0 R] >>\nendobj\n";

		$signatureStart = strlen($pdf) + 200;
		$signatureLength = 100;
		$offset1 = 0;
		$length1 = $signatureStart;
		$offset2 = $signatureStart + $signatureLength;

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$pdf .= "/Reference [ << /Type /SigRef /TransformMethod /DocMDP\n";
		$pdf .= "/TransformParams << /Type /TransformParams /P $pValue /V /1.2 >> >> ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		$targetLength = $offset2 + $length2;
		while (strlen($pdf) < $targetLength) {
			$pdf .= ' ';
		}

		$pdf .= "\n7 0 obj\n<< /FT /Tx /T (TextField1) /V (Modified Value) >>\nendobj\n";
		$pdf .= "xref\n0 8\ntrailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n" . strlen($pdf) . "\n%%EOF";

		return $pdf;
	}

	private function createPdfWithAnnotationModification(int $pValue): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R /Perms << /DocMDP 5 0 R >> >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Annots [7 0 R] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";

		$signatureStart = strlen($pdf) + 200;
		$signatureLength = 100;
		$offset1 = 0;
		$length1 = $signatureStart;
		$offset2 = $signatureStart + $signatureLength;

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$pdf .= "/Reference [ << /Type /SigRef /TransformMethod /DocMDP\n";
		$pdf .= "/TransformParams << /Type /TransformParams /P $pValue /V /1.2 >> >> ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		$targetLength = $offset2 + $length2;
		while (strlen($pdf) < $targetLength) {
			$pdf .= ' ';
		}

		$pdf .= "\n7 0 obj\n<< /Type /Annot /Subtype /Text /Rect [100 100 200 200] /Contents (Comment added) >>\nendobj\n";
		$pdf .= "xref\n0 8\ntrailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n" . strlen($pdf) . "\n%%EOF";

		return $pdf;
	}

	private function createPdfWithStructuralModification(int $pValue): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R /Perms << /DocMDP 5 0 R >> >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R 7 0 R] /Count 2 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";

		$signatureStart = strlen($pdf) + 200;
		$signatureLength = 100;
		$offset1 = 0;
		$length1 = $signatureStart;
		$offset2 = $signatureStart + $signatureLength;

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$pdf .= "/Reference [ << /Type /SigRef /TransformMethod /DocMDP\n";
		$pdf .= "/TransformParams << /Type /TransformParams /P $pValue /V /1.2 >> >> ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		$targetLength = $offset2 + $length2;
		while (strlen($pdf) < $targetLength) {
			$pdf .= ' ';
		}

		$pdf .= "\n7 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "xref\n0 8\ntrailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n" . strlen($pdf) . "\n%%EOF";

		return $pdf;
	}

	private function createPdfWithSubsequentSignature(int $pValue): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R /Perms << /DocMDP 5 0 R >> >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R 8 0 R] >>\nendobj\n";

		$signatureStart = strlen($pdf) + 200;
		$signatureLength = 100;
		$offset1 = 0;
		$length1 = $signatureStart;
		$offset2 = $signatureStart + $signatureLength;

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$pdf .= "/Reference [ << /Type /SigRef /TransformMethod /DocMDP\n";
		$pdf .= "/TransformParams << /Type /TransformParams /P $pValue /V /1.2 >> >> ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		$targetLength = $offset2 + $length2;
		while (strlen($pdf) < $targetLength) {
			$pdf .= ' ';
		}

		$pdf .= "\n7 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= '/ByteRange [ 0 100 200 100 ] /Contents <' . str_repeat('00', 50) . "> >>\nendobj\n";
		$pdf .= "8 0 obj\n<< /FT /Sig /T (Signature2) /V 7 0 R >>\nendobj\n";
		$pdf .= "xref\n0 9\ntrailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n" . strlen($pdf) . "\n%%EOF";

		return $pdf;
	}

	private function createPdfWithDocMdpInSignatureReference(int $pValue): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";

		$signatureStart = strlen($pdf) + 200;
		$signatureLength = 100;
		$offset1 = 0;
		$length1 = $signatureStart;
		$offset2 = $signatureStart + $signatureLength;

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$pdf .= "/Reference [ << /Type /SigRef /TransformMethod /DocMDP\n";
		$pdf .= "/TransformParams << /Type /TransformParams /P $pValue /V /1.2 >> >> ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		$startxref = strlen($pdf);
		$pdf .= "xref\n0 7\ntrailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n$startxref\n%%EOF";

		return $pdf;
	}

	private function createPdfWithApprovalThenCertifyingSignature(): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R 8 0 R] >>\nendobj\n";

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= '/ByteRange [ 0 100 200 100 ] /Contents <' . str_repeat('00', 50) . "> >>\nendobj\n";
		$pdf .= "6 0 obj\n<< /FT /Sig /T (ApprovalSignature) /V 5 0 R >>\nendobj\n";

		$signatureStart = strlen($pdf) + 200;
		$signatureLength = 100;
		$offset1 = 0;
		$length1 = $signatureStart;
		$offset2 = $signatureStart + $signatureLength;

		$pdf .= "7 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$pdf .= "/Reference [ << /Type /SigRef /TransformMethod /DocMDP\n";
		$pdf .= "/TransformParams << /Type /TransformParams /P 1 /V /1.2 >> >> ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "8 0 obj\n<< /FT /Sig /T (CertifyingSignature) /V 7 0 R >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		$startxref = strlen($pdf);
		$pdf .= "xref\n0 9\ntrailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n$startxref\n%%EOF";

		return $pdf;
	}

	private function createPdfWithPageTemplate(int $pValue): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R /Perms << /DocMDP 5 0 R >> >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";

		$signatureStart = strlen($pdf) + 200;
		$signatureLength = 100;
		$offset1 = 0;
		$length1 = $signatureStart;
		$offset2 = $signatureStart + $signatureLength;

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$pdf .= "/Reference [ << /Type /SigRef /TransformMethod /DocMDP\n";
		$pdf .= "/TransformParams << /Type /TransformParams /P $pValue /V /1.2 >> >> ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		$targetLength = $offset2 + $length2;
		while (strlen($pdf) < $targetLength) {
			$pdf .= ' ';
		}

		$pdf .= "\n7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 100] >>\nendobj\n";
		$pdf .= "xref\n0 8\ntrailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n" . strlen($pdf) . "\n%%EOF";

		return $pdf;
	}

	private function createPdfWithIndirectReferencesItiStyle(int $pValue): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";

		$signatureStart = strlen($pdf) + 300;
		$signatureLength = 100;
		$offset1 = 0;
		$length1 = $signatureStart;
		$offset2 = $signatureStart + $signatureLength;

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached\n";
		$pdf .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$pdf .= "/Reference [ 7 0 R ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";

		$pdf .= "7 0 obj\n<< /Type /SigRef /TransformMethod /DocMDP /TransformParams 8 0 R >>\nendobj\n";

		$pdf .= "8 0 obj\n<< /Type /TransformParams /P $pValue /V /1.2 >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		$startxref = strlen($pdf);
		$pdf .= "xref\n0 9\ntrailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n$startxref\n%%EOF";

		return $pdf;
	}

	private function createPdfWithDocMdpVersion12(int $pValue): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";

		$signatureStart = strlen($pdf) + 200;
		$signatureLength = 100;
		$offset1 = 0;
		$length1 = $signatureStart;
		$offset2 = $signatureStart + $signatureLength;

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$pdf .= "/Reference [ << /Type /SigRef /TransformMethod /DocMDP\n";
		$pdf .= "/TransformParams << /Type /TransformParams /P $pValue /V /1.2 >> >> ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		$startxref = strlen($pdf);
		$pdf .= "xref\n0 7\ntrailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n$startxref\n%%EOF";

		return $pdf;
	}

	private function createPdfWithDocMdpWithoutVersion(int $pValue): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";

		$signatureStart = strlen($pdf) + 200;
		$signatureLength = 100;
		$offset1 = 0;
		$length1 = $signatureStart;
		$offset2 = $signatureStart + $signatureLength;

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$pdf .= "/Reference [ << /Type /SigRef /TransformMethod /DocMDP\n";
		$pdf .= "/TransformParams << /Type /TransformParams /P $pValue >> >> ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		$startxref = strlen($pdf);
		$pdf .= "xref\n0 7\ntrailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n$startxref\n%%EOF";

		return $pdf;
	}

	private function createPdfWithDocMdpInvalidVersion(int $pValue, string $version): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";

		$signatureStart = strlen($pdf) + 200;
		$signatureLength = 100;
		$offset1 = 0;
		$length1 = $signatureStart;
		$offset2 = $signatureStart + $signatureLength;

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$pdf .= "/Reference [ << /Type /SigRef /TransformMethod /DocMDP\n";
		$pdf .= "/TransformParams << /Type /TransformParams /P $pValue /V /$version >> >> ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		$startxref = strlen($pdf);
		$pdf .= "xref\n0 7\ntrailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n$startxref\n%%EOF";

		return $pdf;
	}

	private function createPdfWithIndirectReferencesInvalidVersion(int $pValue, string $version): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";

		$signatureStart = strlen($pdf) + 350;
		$signatureLength = 100;
		$offset1 = 0;
		$length1 = $signatureStart;
		$offset2 = $signatureStart + $signatureLength;

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/M (D:20220705145549-03'00')\n";
		$pdf .= "/Reference [7 0 R]\n";
		$pdf .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";

		$pdf .= "7 0 obj\n<< /Type /SigRef /TransformMethod /DocMDP /TransformParams 8 0 R >>\nendobj\n";
		$pdf .= "8 0 obj\n<< /Type /TransformParams /P $pValue /V /$version >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		$startxref = strlen($pdf);
		$pdf .= "xref\n0 9\ntrailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n$startxref\n%%EOF";

		return $pdf;
	}

	// ISO 32000-1 Table 252 validation tests
	public function testRejectsSignatureDictionaryWithoutTypeWhenPresent(): void {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";
		$pdf .= "5 0 obj\n<< /Type /InvalidType /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/Reference [<< /Type /SigRef /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n";
		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";
		$pdf .= "xref\n0 7\ntrailer\n<< /Size 7 /Root 1 0 R >>\n%%EOF";

		$resource = $this->createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1 Table 252: if /Type present in signature dict, must be /Sig');
	}

	public function testRejectsSignatureWithoutFilterEntry(): void {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";
		$pdf .= "5 0 obj\n<< /Type /Sig /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/Reference [<< /Type /SigRef /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n";
		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";
		$pdf .= "xref\n0 7\ntrailer\n<< /Size 7 /Root 1 0 R >>\n%%EOF";

		$resource = $this->createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1 Table 252: /Filter is Required in signature dictionary');
	}

	public function testRejectsSignatureWithoutByteRange(): void {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";
		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/Reference [<< /Type /SigRef /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n";
		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";
		$pdf .= "xref\n0 7\ntrailer\n<< /Size 7 /Root 1 0 R >>\n%%EOF";

		$resource = $this->createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1: ByteRange required when DocMDP transform method is used');
	}

	public function testRejectsMultipleDocMdpSignatures(): void {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R 10 0 R] >>\nendobj\n";

		// First DocMDP signature
		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [0 100 200 100]\n";
		$pdf .= "/Reference [7 0 R] >>\nendobj\n";
		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";
		$pdf .= "7 0 obj\n<< /Type /SigRef /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>\nendobj\n";

		// Second DocMDP signature (INVALID per ISO)
		$pdf .= "8 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [0 100 300 100]\n";
		$pdf .= "/Reference [9 0 R] >>\nendobj\n";
		$pdf .= "9 0 obj\n<< /Type /SigRef /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 3 /V /1.2 >> >>\nendobj\n";
		$pdf .= "10 0 obj\n<< /FT /Sig /T (Signature2) /V 8 0 R >>\nendobj\n";

		$pdf .= "xref\n0 11\ntrailer\n<< /Size 11 /Root 1 0 R >>\n%%EOF";

		$resource = $this->createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1 12.8.2.2.1: A document can contain only one signature field that contains a DocMDP transform method');
	}

	public function testRejectsDocMdpNotFirstSignature(): void {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R 10 0 R] >>\nendobj\n";

		// First signature: regular approval signature (no DocMDP)
		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [0 100 200 100] >>\nendobj\n";
		$pdf .= "6 0 obj\n<< /FT /Sig /T (ApprovalSignature) /V 5 0 R >>\nendobj\n";

		// Second signature: DocMDP certification (INVALID - must be first)
		$pdf .= "7 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [0 100 300 100]\n";
		$pdf .= "/Reference [8 0 R] >>\nendobj\n";
		$pdf .= "8 0 obj\n<< /Type /SigRef /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>\nendobj\n";
		$pdf .= "10 0 obj\n<< /FT /Sig /T (CertificationSignature) /V 7 0 R >>\nendobj\n";

		$pdf .= "xref\n0 11\ntrailer\n<< /Size 11 /Root 1 0 R >>\n%%EOF";

		$resource = $this->createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1 12.8.2.2.1: DocMDP signature shall be the first signed field in the document');
	}

	public function testRejectsSigRefWithoutTransformMethod(): void {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";
		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [0 100 200 100]\n";
		$pdf .= "/Reference [<< /Type /SigRef /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n";
		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";
		$pdf .= "xref\n0 7\ntrailer\n<< /Size 7 /Root 1 0 R >>\n%%EOF";

		$resource = $this->createResourceFromContent($pdf);
		$result = $this->handler->extractDocMdpData($resource);
		fclose($resource);

		$this->assertSame(DocMdpLevel::NOT_CERTIFIED->value, $result['docmdp']['level'], 'ISO 32000-1 Table 253: /TransformMethod is Required in signature reference dictionary');
	}
}
