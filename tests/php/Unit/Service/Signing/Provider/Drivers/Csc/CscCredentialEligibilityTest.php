<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Signing\Provider\Drivers\Csc;

use OCA\Libresign\Service\Signing\Provider\Drivers\Csc\CscCredentialEligibility;
use OCA\Libresign\Tests\Unit\TestCase;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\Application\DTO\SigningCertificateDto;
use OCA\Libresign\Vendor\SignerPHP\PdfSigner\RemoteSigning\DTO\RemoteCertificate;

final class CscCredentialEligibilityTest extends TestCase {
	/** @return RemoteCertificate */
	private function certificate(int $type = OPENSSL_KEYTYPE_RSA, int $bits = 2048, string $usage = 'digitalSignature'): RemoteCertificate {
		$configuration = $type === OPENSSL_KEYTYPE_EC
			? ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']
			: ['private_key_type' => $type, 'private_key_bits' => $bits];
		$key = openssl_pkey_new($configuration);
		self::assertNotFalse($key);
		$req = tempnam(sys_get_temp_dir(), 'csc-eligible-');
		self::assertIsString($req);
		try {
			self::assertNotFalse(file_put_contents($req,
				"[req]\ndistinguished_name = dn\n[dn]\n[v3_signer]\nbasicConstraints = critical,CA:FALSE\nkeyUsage = critical,$usage\n"));
			$csr = openssl_csr_new(['commonName' => 'Synthetic CSC credential'], $key);
			self::assertNotFalse($csr);
			$cert = openssl_csr_sign($csr, null, $key, 1, [
				'config' => $req, 'x509_extensions' => 'v3_signer', 'digest_alg' => 'sha256',
			]);
			self::assertNotFalse($cert);
			self::assertTrue(openssl_x509_export($cert, $pem));
			return new RemoteCertificate('synthetic-credential', new SigningCertificateDto($pem));
		} finally {
			@unlink($req);
		}
	}

	public function testSupportedSyntheticRsaCertificateExposesOnlyPublicMetadata(): void {
		$remote = $this->certificate();
		$details = (new CscCredentialEligibility())->describe($remote);
		self::assertSame('Synthetic CSC credential', $details['subject']);
		self::assertSame('rsa-pkcs1-v1_5', $details['signatureAlgorithm']);
		self::assertSame('sha256', $details['digestAlgorithm']);
		self::assertSame(['pdf-basic', 'pades-baseline-b'], $details['profiles']);
		self::assertSame(openssl_x509_fingerprint($remote->certificate->certificatePem, 'sha256'),
			$details['certificateSha256']);
		self::assertMatchesRegularExpression('/^20\d\d-\d\d-\d\dT/', $details['validTo']);
		self::assertArrayNotHasKey('certificatePem', $details);
		self::assertArrayNotHasKey('privateKey', $details);
	}

	public function testRsaKeyBelow2048BitsIsRejected(): void {
		$remote = $this->certificate(OPENSSL_KEYTYPE_RSA, 1024);
		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('at least 2048');
		(new CscCredentialEligibility())->describe($remote);
	}

	public function testEcdsaCertificateIsRejected(): void {
		$remote = $this->certificate(OPENSSL_KEYTYPE_EC);
		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('RSA certificate');
		(new CscCredentialEligibility())->describe($remote);
	}

	public function testKeyEnciphermentOnlyCertificateIsRejected(): void {
		$remote = $this->certificate(OPENSSL_KEYTYPE_RSA, 2048, 'keyEncipherment');
		$this->expectException(\DomainException::class);
		$this->expectExceptionMessage('does not permit document signatures');
		(new CscCredentialEligibility())->describe($remote);
	}

	public function testInvalidX509CertificateIsRejected(): void {
		$remote = new RemoteCertificate('synthetic-credential', new SigningCertificateDto('invalid certificate'));
		$this->expectException(\DomainException::class);
		(new CscCredentialEligibility())->describe($remote);
	}
}
