<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Service\Signing\Provider\Drivers\Csc;

use SignerPHP\PdfSigner\RemoteSigning\DTO\RemoteCertificate;

/**
 * Check the public certificate properties needed for the CSC v1 subset.
 * The adapter separately enforces CSC status and supported algorithm OID.
 * This is not certificate-path trust or production signer identity validation.
 */
final class CscCredentialEligibility {
	/**
	 * @return array{certificateSha256:string,subject:string,validTo:string,signatureAlgorithm:string,digestAlgorithm:string,profiles:list<string>}
	 */
	public function describe(RemoteCertificate $remote): array {
		$pem = $remote->certificate->certificatePem;
		$certificate = @openssl_x509_read($pem);
		$parsed = $certificate === false ? false : @openssl_x509_parse($certificate);
		if (!is_array($parsed)) {
			throw new \DomainException('CSC signer X.509 certificate is invalid');
		}
		$now = time();
		$validFrom = $parsed['validFrom_time_t'] ?? null;
		$validTo = $parsed['validTo_time_t'] ?? null;
		if (!is_int($validFrom) || !is_int($validTo) || $validFrom > $now || $validTo <= $now) {
			throw new \DomainException('CSC signer certificate is not currently valid');
		}
		$key = @openssl_pkey_get_public($certificate);
		$details = $key === false ? false : openssl_pkey_get_details($key);
		if (!is_array($details) || $details['type'] !== OPENSSL_KEYTYPE_RSA
			|| !isset($details['bits']) || $details['bits'] < 2048) {
			throw new \DomainException('CSC v1 requires an RSA certificate with at least 2048 bits');
		}
		$usage = $parsed['extensions']['keyUsage'] ?? null;
		if ($usage !== null && (!is_string($usage)
			|| (!str_contains($usage, 'Digital Signature') && !str_contains($usage, 'Non Repudiation')))) {
			throw new \DomainException('CSC signer certificate does not permit document signatures');
		}
		$fingerprint = openssl_x509_fingerprint($certificate, 'sha256');
		if (!is_string($fingerprint) || strlen($fingerprint) !== 64) {
			throw new \DomainException('CSC certificate fingerprint cannot be computed');
		}
		$commonName = $parsed['subject']['CN'] ?? '';
		$subject = is_string($commonName) && preg_match('/^[^\x00-\x1f\x7f]{1,128}$/uD', $commonName) === 1
			? $commonName : 'CSC credential';
		return [
			'certificateSha256' => strtolower($fingerprint),
			'subject' => $subject,
			'validTo' => gmdate('Y-m-d\TH:i:s\Z', $validTo),
			'signatureAlgorithm' => 'rsa-pkcs1-v1_5',
			'digestAlgorithm' => 'sha256',
			'profiles' => ['pdf-basic', 'pades-baseline-b'],
		];
	}
}
