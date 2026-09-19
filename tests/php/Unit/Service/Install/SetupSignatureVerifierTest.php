<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Tests\Unit\Service\Install;

use bovigo\vfs\vfsStream;
use OC\IntegrityCheck\Helpers\EnvironmentHelper;
use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\InvalidSignatureException;
use OCA\Libresign\Service\Install\SetupSignatureVerifier;
use OCA\Libresign\Service\Install\SetupTrustMode;
use OCA\Libresign\Vendor\phpseclib4\Crypt\RSA;
use OCA\Libresign\Vendor\phpseclib4\File\X509;
use OCP\ITempManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SetupSignatureVerifierTest extends TestCase {
	private EnvironmentHelper&MockObject $environmentHelper;
	private FileAccessHelper $fileAccessHelper;
	private SetupSignatureVerifier $verifier;
	private ITempManager $tempManager;

	#[\Override]
	protected function setUp(): void {
		$this->environmentHelper = $this->createMock(EnvironmentHelper::class);
		$this->fileAccessHelper = new FileAccessHelper();
		$this->tempManager = \OCP\Server::get(ITempManager::class);
		$this->verifier = new SetupSignatureVerifier(
			$this->environmentHelper,
			$this->fileAccessHelper,
		);
	}

	public function testVerifyCaSignedCertificateAndHashSignature(): void {
		[$rootCertificate, $leafCertificate, $leafPrivateKey] = $this->createCertificateChain(Application::APP_ID);

		vfsStream::setup('home', null, [
			'resources' => [
				'codesigning' => [
					'root.crt' => $rootCertificate,
				],
			],
		]);
		$this->environmentHelper->method('getServerRoot')->willReturn('vfs://home');

		$hashes = ['java' => hash('sha512', 'content')];
		$signatureData = [
			'hashes' => $hashes,
			'signature' => base64_encode(
				RSA::loadPrivateKey($leafPrivateKey)
					->withPadding(RSA::SIGNATURE_PSS)
					->sign(json_encode($hashes)),
			),
			'certificate' => $leafCertificate,
		];

		$certificate = $this->verifier->verify($signatureData, SetupTrustMode::Production);

		$subject = $certificate->getSubjectDN(X509::DN_OPENSSL);
		$this->assertIsArray($subject);
		$this->assertSame(Application::APP_ID, $subject['CN']);
	}

	public function testRejectsCertificateForAnotherScope(): void {
		[$rootCertificate, $leafCertificate, $leafPrivateKey] = $this->createCertificateChain('another-app');

		vfsStream::setup('home', null, [
			'resources' => [
				'codesigning' => [
					'root.crt' => $rootCertificate,
				],
			],
		]);
		$this->environmentHelper->method('getServerRoot')->willReturn('vfs://home');

		$hashes = ['java' => hash('sha512', 'content')];
		$signatureData = [
			'hashes' => $hashes,
			'signature' => base64_encode(
				RSA::loadPrivateKey($leafPrivateKey)
					->withPadding(RSA::SIGNATURE_PSS)
					->sign(json_encode($hashes)),
			),
			'certificate' => $leafCertificate,
		];

		$this->expectException(InvalidSignatureException::class);
		$this->expectExceptionMessage('Certificate is not valid for required scope.');

		$this->verifier->verify($signatureData, SetupTrustMode::Production);
	}

	/**
	 * @return array{string, string, string}
	 */
	private function createCertificateChain(string $leafCommonName): array {
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
		$this->assertNotFalse($rootCertificateResource);
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
		$this->assertNotFalse($leafCertificateResource);
		openssl_x509_export($leafCertificateResource, $leafCertificate);
		openssl_pkey_export($leafKey, $leafPrivateKey);

		return [$rootCertificate, $leafCertificate, $leafPrivateKey];
	}
}
