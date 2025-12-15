<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Fixtures;

class PdfGenerator {
	public static function createMinimalPdf(): string {
		return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
			. "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
			. "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n"
			. "xref\n0 4\ntrailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n190\n%%EOF";
	}

	public static function createPdfWithDocMdp(int $pValue, bool $withModifications = false): string {
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

	public static function createPdfWithFormFieldModification(int $pValue): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R /Perms << /DocMDP 5 0 R >> >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Annots [9 0 R] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R 9 0 R] >>\nendobj\n";

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

		$pdf .= "\n9 0 obj\n<< /FT /Tx /T (TextField1) /V (Modified) /Rect [100 100 200 120] >>\nendobj\n";
		$pdf .= "xref\n0 10\ntrailer\n<< /Size 10 /Root 1 0 R >>\nstartxref\n" . strlen($pdf) . "\n%%EOF";

		return $pdf;
	}

	public static function createPdfWithAnnotationModification(int $pValue): string {
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

		$pdf .= "\n7 0 obj\n<< /Type /Annot /Subtype /Text /Rect [100 100 200 200] >>\nendobj\n";
		$pdf .= "xref\n7 1\ntrailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n" . strlen($pdf) . "\n%%EOF";

		return $pdf;
	}

	public static function createResourceFromContent(string $content) {
		$resource = tmpfile();
		fwrite($resource, $content);
		rewind($resource);
		return $resource;
	}

	/**
	 * FPDI-compliant PDF structure (for FileService validation)
	 * Creates a complete PDF with proper xref table and stream objects
	 */
	public static function createCompletePdfStructure(int $pValue, bool $withModifications = false): string {
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

	/**
	 * DocMDP signature without /V entry (ISO 32000-1 violation)
	 */
	public static function createPdfWithDocMdpWithoutVersion(int $pValue): string {
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
	 * DocMDP signature with non-standard version
	 */
	public static function createPdfWithDocMdpInvalidVersion(int $pValue, string $version): string {
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
	 * PDF with page added after signing (tests P=1 rejection)
	 */
	public static function createPdfWithStructuralModification(int $pValue): string {
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
	 * PDF with second signature added after DocMDP (tests ISO 32000-2 §12.8.2.3)
	 */
	public static function createPdfWithSubsequentSignature(int $pValue): string {
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
	 * DocMDP in /Reference but not in /Perms (tests §12.8.2.2 validation)
	 */
	public static function createPdfWithDocMdpInSignatureReference(int $pValue): string {
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
	 * Approval signature, then certification (violates "DocMDP must be first")
	 */
	public static function createPdfWithApprovalThenCertifyingSignature(): string {
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
	 * PDF with XObject form added after signing (tests P=3 acceptance)
	 */
	public static function createPdfWithPageTemplate(int $pValue): string {
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
	 * ITI's indirect reference style: /Reference [ 7 0 R ] instead of inline dict
	 */
	public static function createPdfWithIndirectReferencesItiStyle(int $pValue): string {
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
	 * Indirect references with invalid /V value
	 */
	public static function createPdfWithIndirectReferencesInvalidVersion(int $pValue, string $version): string {
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
	 * Signature with wrong /Type (not /Sig)
	 */
	public static function createPdfWithInvalidSignatureType(): string {
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
	 * Signature without /Filter entry
	 */
	public static function createPdfWithoutFilterEntry(): string {
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
	 * Signature without /ByteRange
	 */
	public static function createPdfWithoutByteRange(): string {
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
	 * Two signatures both with DocMDP (forbidden per ISO 32000-2 §12.8.2.2)
	 */
	public static function createPdfWithMultipleDocMdpSignatures(): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R 10 0 R] >>\nendobj\n";

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [0 100 200 100]\n";
		$pdf .= "/Reference [7 0 R] >>\nendobj\n";
		$pdf .= "6 0 obj\n<< /FT /Sig /T (Signature1) /V 5 0 R >>\nendobj\n";
		$pdf .= "7 0 obj\n<< /Type /SigRef /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>\nendobj\n";

		$pdf .= "8 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [0 100 300 100]\n";
		$pdf .= "/Reference [9 0 R] >>\nendobj\n";
		$pdf .= "9 0 obj\n<< /Type /SigRef /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 3 /V /1.2 >> >>\nendobj\n";
		$pdf .= "10 0 obj\n<< /FT /Sig /T (Signature2) /V 8 0 R >>\nendobj\n";

		$pdf .= "xref\n0 11\ntrailer\n<< /Size 11 /Root 1 0 R >>\n%%EOF";
		return $pdf;
	}

	/**
	 * DocMDP not on first signature (violates first-signature-only rule)
	 */
	public static function createPdfWithDocMdpNotFirst(): string {
		$pdf = "%PDF-1.7\n";
		$pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 4 0 R >>\nendobj\n";
		$pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\n";
		$pdf .= "4 0 obj\n<< /SigFlags 3 /Fields [6 0 R 10 0 R] >>\nendobj\n";

		$pdf .= "5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [0 100 200 100] >>\nendobj\n";
		$pdf .= "6 0 obj\n<< /FT /Sig /T (ApprovalSignature) /V 5 0 R >>\nendobj\n";

		$pdf .= "7 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached\n";
		$pdf .= "/ByteRange [0 100 300 100]\n";
		$pdf .= "/Reference [8 0 R] >>\nendobj\n";
		$pdf .= "8 0 obj\n<< /Type /SigRef /TransformMethod /DocMDP /TransformParams << /Type /TransformParams /P 2 /V /1.2 >> >>\nendobj\n";
		$pdf .= "10 0 obj\n<< /FT /Sig /T (CertificationSignature) /V 7 0 R >>\nendobj\n";

		$pdf .= "xref\n0 11\ntrailer\n<< /Size 11 /Root 1 0 R >>\n%%EOF";
		return $pdf;
	}

	/**
	 * SigRef without /TransformMethod (ISO 32000-1 violation)
	 */
	public static function createPdfWithSigRefWithoutTransformMethod(): string {
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
