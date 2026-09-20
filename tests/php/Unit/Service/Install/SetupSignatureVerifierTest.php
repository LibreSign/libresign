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
use OCA\Libresign\Tests\Mock\CertificateChainFixture;
use OCA\Libresign\Service\Install\SetupTrustMode;
use OCA\Libresign\Vendor\phpseclib4\Crypt\RSA;
use OCA\Libresign\Vendor\phpseclib4\File\X509;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SetupSignatureVerifierTest extends TestCase {
	private EnvironmentHelper&MockObject $environmentHelper;
	private FileAccessHelper $fileAccessHelper;
	private SetupSignatureVerifier $verifier;
	private CertificateChainFixture $certificateChainFixture;

	#[\Override]
	protected function setUp(): void {
		$this->environmentHelper = $this->createMock(EnvironmentHelper::class);
		$this->fileAccessHelper = new FileAccessHelper();
		$this->certificateChainFixture = new CertificateChainFixture(\OCP\Server::get(ITempManager::class));
		$this->verifier = new SetupSignatureVerifier(
			$this->environmentHelper,
			$this->fileAccessHelper,
		);
	}

	public function testVerifyCaSignedCertificateAndHashSignature(): void {
		$chain = $this->certificateChainFixture->create(Application::APP_ID);
		$rootCertificate = $chain['rootCertificate'];
		$leafCertificate = $chain['leafCertificate'];
		$leafPrivateKey = $chain['leafPrivateKey'];

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
		$chain = $this->certificateChainFixture->create('another-app');
		$rootCertificate = $chain['rootCertificate'];
		$leafCertificate = $chain['leafCertificate'];
		$leafPrivateKey = $chain['leafPrivateKey'];

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

}