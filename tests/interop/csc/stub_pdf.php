<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Only the development test explicitly opts in to the SignerPHP dependency.
$autoload = getenv('LIBRESIGN_CSC_INTEROP_AUTOLOAD') ?: dirname(__DIR__, 3) . '/vendor/autoload.php';
require $autoload;

use SignerPHP\PdfSigner\Application\DTO\PdfContentDto;
use SignerPHP\PdfSigner\Application\DTO\SignatureActorDto;
use SignerPHP\PdfSigner\Application\DTO\SignatureMetadataDto;
use SignerPHP\PdfSigner\Application\DTO\SignatureProfile;
use SignerPHP\PdfSigner\Application\DTO\SigningCertificateDto;
use SignerPHP\PdfSigner\Application\DTO\SigningContextDto;
use SignerPHP\PdfSigner\Application\DTO\SigningOptionsDto;
use SignerPHP\PdfSigner\Infrastructure\Native\Service\ExternalPdfSigningService;

$mode = $argv[1] ?? '';
$dir = getenv('LIBRESIGN_CSC_INTEROP_DIR');
if (!is_string($dir) || $dir === '' || !is_dir($dir)) {
	throw new RuntimeException('Set LIBRESIGN_CSC_INTEROP_DIR to a private scratch directory');
}
umask(0077);
$statePath = $dir . '/signed-state.json';
$pdfPath = dirname(__DIR__, 2) . '/php/fixtures/pdfs/small_valid.pdf';
$certPath = $dir . '/provider-certificate.pem';
$signPath = $dir . '/provider-signature.bin';
$outPdf = $dir . '/provider-signed.pdf';

$engine = new ExternalPdfSigningService();
if ($mode === 'prepare') {
	$pdf = file_get_contents($pdfPath);
	$pem = file_get_contents($certPath);
	if (!is_string($pdf) || !str_starts_with($pdf, '%PDF-') || !is_string($pem)) {
		throw new RuntimeException('No test PDF or CSC public certificate');
	}
	$context = new SigningContextDto(
		new PdfContentDto($pdf),
		new SigningOptionsDto(
			metadata: new SignatureMetadataDto(actor: new SignatureActorDto(name: 'LibreSign CSC QA')),
			signatureProfile: SignatureProfile::PadesBaselineB,
			useDefaultAppearance: false,
		),
		new SigningCertificateDto($pem),
	);
	$prepared = $engine->prepare($context);
	$digest = $prepared->payload->input();
	if (strlen($digest) !== 32) {
		throw new RuntimeException('Prepared CMS signed attributes must be SHA256');
	}
	file_put_contents($statePath, $prepared->state);
	file_put_contents($dir . '/prepare-hash.bin', $digest);
	echo "PASS: SignerPHP prepared PDF / PAdES B-B, 32-byte CMS signed-attributes digest\n";
} elseif ($mode === 'complete') {
	$state = file_get_contents($statePath);
	$raw = file_get_contents($signPath);
	$pem = file_get_contents($certPath);
	$digest = file_get_contents($dir . '/prepare-hash.bin');
	if (!is_string($state) || !is_string($raw) || !is_string($pem) || !is_string($digest)) {
		throw new RuntimeException('Incomplete signing state');
	}
	$public = openssl_pkey_get_public($pem);
	$digestInfo = hex2bin('3031300d060960864801650304020105000420') . $digest;
	if ($public === false || openssl_public_decrypt($raw, $recovered, $public, OPENSSL_PKCS1_PADDING) === false
		|| !hash_equals($digestInfo, $recovered)) {
		throw new RuntimeException('Remote RSA signature did not sign the expected prepared digest');
	}
	$signedPdf = $engine->complete($state, $raw);
	if (!str_starts_with($signedPdf, '%PDF-')) {
		throw new RuntimeException('SignerPHP did not produce a PDF');
	}
	file_put_contents($outPdf, $signedPdf);
	echo "PASS: verified provider RSA signature and embedded it into a PAdES B-B PDF\n";
	echo 'OUTPUT: ' . $outPdf . "\n";
} else {
	throw new InvalidArgumentException('Usage: php stub_pdf.php prepare|complete');
}
