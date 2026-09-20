<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Install;

use OC\IntegrityCheck\Helpers\EnvironmentHelper;
use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OCA\Libresign\AppInfo\Application;
use OCA\Libresign\Exception\InvalidSignatureException;
use OCA\Libresign\Exception\LibresignException;
use OCA\Libresign\Vendor\phpseclib4\Crypt\RSA;
use OCA\Libresign\Vendor\phpseclib4\File\X509;

final class SetupSignatureVerifier {
	public function __construct(
		private EnvironmentHelper $environmentHelper,
		private FileAccessHelper $fileAccessHelper,
	) {
	}

	/**
	 * @param array{hashes: array<string, string>, signature: string, certificate: string} $signatureData
	 */
	public function verify(array $signatureData, SetupTrustMode $trustMode): X509 {
		$certificate = $signatureData['certificate'] ?? '';
		if ($certificate === '') {
			throw new InvalidSignatureException('Certificate not found in signature data.');
		}

		$x509 = X509::load($certificate);
		$this->validateCertificate($x509, $trustMode);
		$this->validateScope($x509);
		$this->validateFileSignature($x509, $signatureData['hashes'], $signatureData['signature']);

		return $x509;
	}

	private function validateCertificate(X509 $x509, SetupTrustMode $trustMode): void {
		$previousCAs = X509::getCAs();
		X509::clearCAStore();
		try {
			foreach ($this->splitCerts($this->getRootCertificate($trustMode)) as $rootCert) {
				X509::addCA($rootCert);
			}
			if (!$x509->validateSignature()) {
				throw new InvalidSignatureException('Certificate is not valid.');
			}
		} finally {
			X509::clearCAStore();
			foreach ($previousCAs as $previousCA) {
				X509::addCA($previousCA);
			}
		}
	}

	private function validateScope(X509 $x509): void {
		$subject = $x509->getSubjectDN(X509::DN_OPENSSL);
		$commonName = is_array($subject) ? ($subject['CN'] ?? '') : '';
		if ($commonName !== Application::APP_ID && $commonName !== 'core') {
			throw new InvalidSignatureException(
				sprintf(
					'Certificate is not valid for required scope. (Requested: %s, current: CN=%s)',
					Application::APP_ID,
					$commonName,
				),
			);
		}
	}

	/**
	 * @param array<string, string> $hashes
	 */
	private function validateFileSignature(X509 $x509, array $hashes, string $signature): void {
		$publicKey = $x509->getPublicKey();
		if (!$publicKey instanceof RSA) {
			throw new InvalidSignatureException('Certificate public key is not RSA.');
		}

		$decodedSignature = base64_decode($signature, true);
		if ($decodedSignature === false) {
			throw new InvalidSignatureException('Signature could not get decoded.');
		}

		$rsa = $publicKey->withPadding(RSA::SIGNATURE_PSS);
		if (!$rsa->verify(json_encode($hashes), $decodedSignature)) {
			throw new InvalidSignatureException('Signature could not get verified.');
		}
	}

	private function getRootCertificate(SetupTrustMode $trustMode): string {
		if ($trustMode === SetupTrustMode::Development) {
			$localCert = __DIR__ . '/../../../build/tools/certificates/local/root.crt';
			if (!file_exists($localCert)) {
				throw new LibresignException('Local development root certificate not found at ' . $localCert);
			}
			return (string)file_get_contents($localCert);
		}

		$rootCertificatePath = $this->environmentHelper->getServerRoot() . '/resources/codesigning/root.crt';
		$rootCertificate = $this->fileAccessHelper->file_get_contents($rootCertificatePath);
		if (!is_string($rootCertificate)) {
			throw new LibresignException('Root certificate not found at ' . $rootCertificatePath);
		}
		return $rootCertificate;
	}

	/**
	 * @return list<string>
	 */
	private function splitCerts(string $cert): array {
		preg_match_all(
			'([\-]{3,}[\S\ ]+?[\-]{3,}[\S\s]+?[\-]{3,}[\S\ ]+?[\-]{3,})',
			$cert,
			$matches,
		);
		return $matches[0];
	}
}
