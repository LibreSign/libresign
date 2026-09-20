<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Mock;

use OCP\ITempManager;
use PHPUnit\Framework\Assert;

final class CertificateChainFixture {
	public function __construct(
		private ITempManager $tempManager,
	) {
	}

	/**
	 * @return array{rootCertificate: string, leafCertificate: string, leafPrivateKey: string}
	 */
	public function create(string $leafCommonName): array {
		$configFile = $this->tempManager->getTemporaryFile('.cnf');
		file_put_contents($configFile, <<<'CONFIG'
[ req ]
distinguished_name = req_distinguished_name
prompt = no

[ req_distinguished_name ]
CN = LibreSign Test

[ v3_ca ]
basicConstraints = critical, CA:true
keyUsage = critical, keyCertSign, cRLSign
subjectKeyIdentifier = hash

[ v3_leaf ]
basicConstraints = critical, CA:false
keyUsage = critical, digitalSignature
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid,issuer
CONFIG);

		$rootKey = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);
		$rootCsr = openssl_csr_new(
			['commonName' => 'LibreSign Test Root'],
			$rootKey,
			['config' => $configFile, 'digest_alg' => 'sha256'],
		);
		$rootCertificateResource = openssl_csr_sign(
			$rootCsr,
			null,
			$rootKey,
			1,
			[
				'config' => $configFile,
				'digest_alg' => 'sha256',
				'x509_extensions' => 'v3_ca',
			],
		);
		Assert::assertNotFalse($rootCertificateResource);
		openssl_x509_export($rootCertificateResource, $rootCertificate);

		$leafKey = openssl_pkey_new([
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);
		$leafCsr = openssl_csr_new(
			['commonName' => $leafCommonName],
			$leafKey,
			['config' => $configFile, 'digest_alg' => 'sha256'],
		);
		$leafCertificateResource = openssl_csr_sign(
			$leafCsr,
			$rootCertificateResource,
			$rootKey,
			1,
			[
				'config' => $configFile,
				'digest_alg' => 'sha256',
				'x509_extensions' => 'v3_leaf',
			],
		);
		Assert::assertNotFalse($leafCertificateResource);
		openssl_x509_export($leafCertificateResource, $leafCertificate);
		openssl_pkey_export($leafKey, $leafPrivateKey);

		return [
			'rootCertificate' => $rootCertificate,
			'leafCertificate' => $leafCertificate,
			'leafPrivateKey' => $leafPrivateKey,
		];
	}
}
