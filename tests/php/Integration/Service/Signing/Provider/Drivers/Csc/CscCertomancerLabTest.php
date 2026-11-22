<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Integration\Service\Signing\Provider\Drivers\Csc;

use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SignerPHP\PdfSigner\Infrastructure\Native\Service\CurlHttpClient;
use SignerPHP\PdfSigner\Presentation\Signer;

/** Opt-in only. CSC_LAB_SYNTHETIC_ONLY=1 and loopback Certomancer CSC Dummy. */
final class CscCertomancerLabTest extends TestCase {
	#[DataProvider('profiles')]
	public function testSyntheticCertomancerSignsValidPdf(bool $pades): void {
		$url = getenv('CSC_LAB_BASE_URL');
		$id = getenv('CSC_LAB_CREDENTIAL_ID');
		if (getenv('CSC_LAB_SYNTHETIC_ONLY') !== '1' || !is_string($url) || $url === ''
			|| !is_string($id) || $id === '') {
			$this->markTestSkipped('CSC Certomancer integration is opt-in and synthetic-only.');
		}
		$adapter = CscIntegration::forSyntheticLab(new CurlHttpClient(), $url);
		$certificates = $adapter->certificates($id);
		$this->assertCount(1, $certificates);
		$certificate = $certificates[0];
		$pdf = file_get_contents(dirname(__DIR__, 6) . '/fixtures/pdfs/small_valid.pdf');
		$this->assertIsString($pdf);
		$builder = Signer::externalSigner()
			->withPdfContent($pdf)
			->withCertificate($certificate->certificate->certificatePem, $certificate->certificate->chainPem)
			->withoutDefaultAppearance();
		if ($pades) {
			$builder->withPadesBaselineB();
		}
		$prepared = $builder->prepare();
		$signature = $adapter->sign($prepared->payload, $certificate);
		$signed = $builder->complete($prepared->state, $signature->bytes);
		$validation = Signer::validation()->withPdfContent($signed)->disableTrustChainValidation()->validate();
		$this->assertTrue($validation->allValid);
		$this->assertStringContainsString('/ByteRange', $signed);
		if ($pades) {
			$this->assertStringContainsString('/ETSI.CAdES.detached', $signed);
		}
	}

	public static function profiles(): array {
		return ['PDF Basic' => [false], 'PAdES B-B' => [true]];
	}
}
