<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit;

/**
 * Shared PDF fixtures for tests
 *
 * This trait provides helper methods to create various types of PDF documents
 * for testing purposes, including PDFs with different DocMDP levels, modifications,
 * and validation scenarios.
 */
trait PdfFixtureTrait {
	/**
	 * Create a minimal unsigned PDF (valid but minimal structure)
	 */
	protected function createMinimalPdf(): string {
		return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
			. "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
			. "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
			. "xref\n0 4\ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n190\n%%EOF";
	}

	/**
	 * Create a complete PDF with DocMDP signature
	 *
	 * This creates a more complete PDF structure that passes FPDI validation
	 * and includes proper DocMDP transformation parameters.
	 *
	 * @param int $pValue DocMDP permission level (0=not certified, 1=no changes, 2=form filling, 3=form+annotations)
	 * @param bool $withModifications Whether to add modifications after signature
	 * @return string PDF content as string
	 */
	/**
	 * Create PDF with DocMDP signature
	 *
	 * Uses complete FPDI-valid structure for FileService tests,
	 * or minimal structure for DocMdpHandler tests.
	 */
	protected function createPdfWithDocMdp(int $pValue, bool $withModifications = false): string {
		// FileService needs FPDI-valid PDF (has validatePdfStringWithFpdi)
		if (str_contains(static::class, 'FileServiceTest')) {
			return $this->createCompletePdfStructure($pValue, $withModifications);
		}

		// DocMdpHandler only needs minimal structure
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

	/**
	 * FPDI-compliant PDF structure (for FileService validation)
	 *
	 * FileService.validateFileContent() uses Smalot PDF parser which requires:
	 * - Valid xref table with correct offsets
	 * - Content streams
	 * - Font dictionaries
	 * - Proper trailer
	 */
	private function createCompletePdfStructure(int $pValue, bool $withModifications): string {
		$pdf = "%PDF-1.7\n";

		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R /Perms << /DocMDP 5 0 R >> >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 7 0 R /Resources << /Font << /F1 8 0 R >> >> >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";

		$currentLength = strlen($pdf);
		$signatureObjectStart = $currentLength + 150;
		$signatureLength = 8192;
		$offset1 = 0;
		$length1 = $signatureObjectStart;
		$offset2 = $signatureObjectStart + $signatureLength;

		$sigObj = "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$sigObj .= "/ByteRange [ $offset1 $length1 $offset2 PLACEHOLDER_LENGTH2 ]\n";
		$sigObj .= "/Reference [ << /Type /SigRef /TransformMethod /DocMDP\n";
		$sigObj .= "/TransformParams << /Type /TransformParams /P $pValue /V /1.2 >> >> ]\n";
		$sigObj .= '/Contents <' . str_repeat('30', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= $sigObj;
		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R /Rect [0 0 0 0] /P 3 0 R >>\nendobj\n";
		$pdf .= "7 0 obj\n<< /Length 44 >>\nstream\nBT\n/F1 12 Tf\n100 700 Td\n(Test Document) Tj\nET\nendstream\nendobj\n";
		$pdf .= "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

		$length2 = 300;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		if ($withModifications) {
			$targetLength = $offset2 + $length2;
			while (strlen($pdf) < $targetLength) {
				$pdf .= ' ';
			}
			$pdf .= "\n9 0 obj\n<< /Type /Annot /Subtype /Text /Rect [100 100 200 200] >>\nendobj\n";
		}

		$xrefPos = strlen($pdf);
		$objectCount = $withModifications ? 10 : 9;
		$pdf .= "xref\n0 $objectCount\n";
		$pdf .= "0000000000 65535 f \n";
		$pdf .= "0000000015 00000 n \n";
		$pdf .= "0000000115 00000 n \n";
		$pdf .= "0000000174 00000 n \n";
		$pdf .= "0000000308 00000 n \n";
		$pdf .= sprintf("%010d 00000 n \n", $currentLength);
		$pdf .= sprintf("%010d 00000 n \n", $currentLength + strlen($sigObj));
		$pdf .= sprintf("%010d 00000 n \n", $currentLength + strlen($sigObj) + 100);
		$pdf .= sprintf("%010d 00000 n \n", $currentLength + strlen($sigObj) + 200);
		if ($withModifications) {
			$pdf .= sprintf("%010d 00000 n \n", $xrefPos - 100);
		}

		$pdf .= "trailer\n<< /Size $objectCount /Root 1 0 R >>\n";
		$pdf .= "startxref\n$xrefPos\n%%EOF\n";

		return $pdf;
	}

	protected function createPdfWithFormFieldModification(int $pValue): string {
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

	/**
	 * Create PDF with annotation modification
	 */
	protected function createPdfWithAnnotationModification(int $pValue): string {
		return $this->createPdfWithDocMdp($pValue, withModifications: true);
	}

	/**
	 * Create PDF with DocMDP but without version in TransformParams
	 */
	protected function createPdfWithDocMdpWithoutVersion(int $pValue): string {
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

		// Missing /V in TransformParams
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

	/**
	 * Create PDF with DocMDP with invalid version
	 */
	protected function createPdfWithDocMdpInvalidVersion(int $pValue, string $version): string {
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
		$pdf .= "/TransformParams << /Type /TransformParams /P $pValue /V /$version >> >> ]\n";
		$pdf .= '/Contents <' . str_repeat('00', $signatureLength / 2) . "> >>\nendobj\n";

		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";

		$length2 = 200;
		$pdf = str_replace('PLACEHOLDER_LENGTH2', (string)$length2, $pdf);

		$startxref = strlen($pdf);
		$pdf .= "xref\n0 7\ntrailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n$startxref\n%%EOF";

		return $pdf;
	}

	/**
	 * Create PDF with DocMDP version 1.2 (valid per ICP-Brasil)
	 */
	protected function createPdfWithDocMdpVersion12(int $pValue): string {
		return $this->createPdfWithDocMdp($pValue);
	}

	/**
	 * Convenience methods for specific DocMDP levels
	 */
	protected function createPdfWithDocMdpLevel0(): string {
		return $this->createPdfWithDocMdp(0);
	}

	protected function createPdfWithDocMdpLevel1(): string {
		return $this->createPdfWithDocMdp(1);
	}

	protected function createPdfWithDocMdpLevel2(): string {
		return $this->createPdfWithDocMdp(2);
	}

	protected function createPdfWithDocMdpLevel3(): string {
		return $this->createPdfWithDocMdp(3);
	}

	/**
	 * Create resource from PDF content (for DocMdpHandler tests)
	 */
	protected function createResourceFromContent(string $content) {
		$resource = tmpfile();
		fwrite($resource, $content);
		fseek($resource, 0);
		return $resource;
	}

	/**
	 * Create PDF with structural modification (adding a new page)
	 */
	protected function createPdfWithStructuralModification(int $pValue): string {
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

	/**
	 * Create PDF with subsequent signature (multiple signatures)
	 */
	protected function createPdfWithSubsequentSignature(int $pValue): string {
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

	/**
	 * Create PDF with DocMDP in signature Reference (without /Perms)
	 */
	protected function createPdfWithDocMdpInSignatureReference(int $pValue): string {
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

	/**
	 * Create PDF with approval signature followed by certifying signature
	 */
	protected function createPdfWithApprovalThenCertifyingSignature(): string {
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

	/**
	 * Create PDF with page template (XObject Form)
	 */
	protected function createPdfWithPageTemplate(int $pValue): string {
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

	/**
	 * Create PDF with indirect references (ITI style)
	 */
	protected function createPdfWithIndirectReferencesItiStyle(int $pValue): string {
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

	/**
	 * Create PDF with indirect references and invalid version (for testing rejection)
	 */
	protected function createPdfWithIndirectReferencesInvalidVersion(int $pValue, string $version): string {
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

	/**
	 * ISO 32000-1 Table 252 validation: Signature dictionary with invalid /Type
	 */
	protected function createPdfWithInvalidSignatureType(): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";
		$pdf .= "5 0 obj\n<< /Type /InvalidType /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/Reference [<< /Type /SigRef /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n";
		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";
		$pdf .= "xref\n0 7\ntrailer\n<< /Size 7 /Root 1 0 R >>\n%%EOF";
		return $pdf;
	}

	/**
	 * ISO 32000-1 Table 252 validation: Signature dictionary without /Filter
	 */
	protected function createPdfWithoutFilterEntry(): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";
		$pdf .= "5 0 obj\n<< /Type /Sig /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/Reference [<< /Type /SigRef /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n";
		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";
		$pdf .= "xref\n0 7\ntrailer\n<< /Size 7 /Root 1 0 R >>\n%%EOF";
		return $pdf;
	}

	/**
	 * ISO 32000-1: Signature without required /ByteRange
	 */
	protected function createPdfWithoutByteRange(): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R] >>\nendobj\n";
		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/Reference [<< /Type /SigRef /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>] >>\nendobj\n";
		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";
		$pdf .= "xref\n0 7\ntrailer\n<< /Size 7 /Root 1 0 R >>\n%%EOF";
		return $pdf;
	}

	/**
	 * ISO 32000-1 12.8.2.2.1: Multiple DocMDP signatures (invalid)
	 */
	protected function createPdfWithMultipleDocMdpSignatures(): string {
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
		return $pdf;
	}

	/**
	 * ISO 32000-1 12.8.2.2.1: DocMDP not as first signature (invalid)
	 */
	protected function createPdfWithDocMdpNotFirst(): string {
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
		return $pdf;
	}

	/**
	 * ISO 32000-1 Table 253: SigRef without /TransformMethod
	 */
	protected function createPdfWithSigRefWithoutTransformMethod(): string {
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
		return $pdf;
	}
}
