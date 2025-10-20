<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Libresign\Handler\CertificateEngine;

use InvalidArgumentException;

trait OrderCertificatesTrait {
	public function orderCertificates(array $certificates): array {
		if (empty($certificates)) {
			throw new InvalidArgumentException('Certificate list cannot be empty');
		}

		$this->ensureValidStructure($certificates);

		return match (true) {
			count($certificates) === 1 => $certificates,
			default => $this->buildChain($certificates)
		};
	}

	private function buildChain(array $certificates): array {
		$leaf = $this->findLeafCertificate($certificates);
		if (!$leaf) {
			return $certificates;
		}

		$ordered = [$leaf];
		$remaining = $this->excludeCertificate($certificates, $leaf);

		while ($remaining && !$this->isRootCertificate(end($ordered))) {
			[$next, $remaining] = $this->findIssuer(end($ordered), $remaining);
			if (!$next) {
				break;
			}
			$ordered[] = $next;
		}

		return [...$ordered, ...$remaining];
	}

	private function ensureValidStructure(array $certificates): void {
		foreach ($certificates as $cert) {
			if (!is_array($cert) || !isset($cert['subject'], $cert['issuer'], $cert['name'])) {
				throw new InvalidArgumentException('Invalid certificate structure. Certificate must have "subject", "issuer", and "name".');
			}
			if (!is_array($cert['subject']) || !is_array($cert['issuer'])) {
				throw new InvalidArgumentException('Invalid certificate structure. Certificate must have "subject", "issuer", and "name".');
			}
		}

		$names = array_column($certificates, 'name');
		if (count($names) !== count(array_unique($names))) {
			throw new InvalidArgumentException('Duplicate certificate names detected');
		}
	}

	private function findLeafCertificate(array $certificates): ?array {
		$issuers = [];
		foreach ($certificates as $cert) {
			if (isset($cert['issuer'])) {
				$issuers[] = $this->normalizeDistinguishedName($cert['issuer']);
			}
		}

		foreach ($certificates as $cert) {
			if (!isset($cert['subject'])) {
				continue;
			}
			$subject = $this->normalizeDistinguishedName($cert['subject']);
			if (!in_array($subject, $issuers, true)) {
				return $cert;
			}
		}

		return $certificates[0] ?? null;
	}

	private function findIssuer(array $cert, array $certificates): array {
		foreach ($certificates as $index => $candidate) {
			if ($this->isIssuedBy($cert, $candidate)) {
				unset($certificates[$index]);
				return [$candidate, array_values($certificates)];
			}
		}
		return [null, $certificates];
	}

	private function excludeCertificate(array $certificates, array $toExclude): array {
		return array_values(array_filter($certificates, fn ($cert) => $cert['name'] !== $toExclude['name']));
	}

	private function isRootCertificate(array $cert): bool {
		if (!isset($cert['subject'], $cert['issuer']) || !is_array($cert['subject']) || !is_array($cert['issuer'])) {
			return false;
		}
		return $this->normalizeDistinguishedName($cert['subject']) === $this->normalizeDistinguishedName($cert['issuer']);
	}

	private function isIssuedBy(array $child, array $parent): bool {
		if (!isset($child['issuer'], $parent['subject']) || !is_array($child['issuer']) || !is_array($parent['subject'])) {
			return false;
		}
		return $this->normalizeDistinguishedName($child['issuer']) === $this->normalizeDistinguishedName($parent['subject']);
	}

	private function normalizeDistinguishedName(array $dn): string {
		ksort($dn);
		return json_encode($dn, JSON_THROW_ON_ERROR);
	}

	public function validateCertificateChain(array $certificates): array {
		return [
			'valid' => $this->isValidChain($certificates),
			'hasRoot' => $this->hasRootCertificate($certificates),
			'isComplete' => $this->isCompleteChain($certificates),
			'length' => count($certificates),
		];
	}

	private function isValidChain(array $certificates): bool {
		if (empty($certificates)) {
			return false;
		}

		foreach ($certificates as $cert) {
			if (!is_array($cert) || !isset($cert['subject'], $cert['issuer'], $cert['name'])
				|| !is_array($cert['subject']) || !is_array($cert['issuer'])
				|| empty($cert['subject']['CN']) || empty($cert['issuer']['CN'])) {
				return false;
			}
		}

		return true;
	}

	private function hasRootCertificate(array $certificates): bool {
		foreach ($certificates as $cert) {
			if ($this->isRootCertificate($cert)) {
				return true;
			}
		}
		return false;
	}

	private function isCompleteChain(array $certificates): bool {
		if (!$this->hasRootCertificate($certificates)) {
			return false;
		}

		$ordered = $this->orderCertificates($certificates);
		for ($i = 0; $i < count($ordered) - 1; $i++) {
			if (!$this->isIssuedBy($ordered[$i], $ordered[$i + 1])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate X.509 extensions in certificate chain
	 */
	public function validateChainExtensions(array $certificates): array {
		$results = [
			'valid' => true,
			'errors' => [],
			'certificates' => []
		];

		foreach ($certificates as $index => $cert) {
			$certResult = $this->validateCertificateExtensions($cert, $certificates);
			$results['certificates'][$index] = $certResult;
			
			if (!$certResult['valid']) {
				$results['valid'] = false;
				$results['errors'] = array_merge($results['errors'], $certResult['errors']);
			}
		}

		return $results;
	}

	private function validateCertificateExtensions(array $cert, array $chain): array {
		$result = [
			'valid' => true,
			'errors' => [],
			'extensions' => []
		];

		if (!isset($cert['name'])) {
			$result['errors'][] = 'Certificate missing name field';
			$result['valid'] = false;
			return $result;
		}

		if (!file_exists($cert['name'])) {
			$result['errors'][] = 'Certificate file not found: ' . $cert['name'];
			$result['valid'] = false;
			return $result;
		}

		$certContent = file_get_contents($cert['name']);
		if ($certContent === false) {
			$result['errors'][] = 'Failed to read certificate file: ' . $cert['name'];
			$result['valid'] = false;
			return $result;
		}

		$certData = openssl_x509_parse($certContent);
		if (!$certData) {
			$result['errors'][] = 'Failed to parse certificate: ' . $cert['name'];
			$result['valid'] = false;
			return $result;
		}

		$this->validateBasicConstraints($certData, $result);
		$this->validateKeyUsage($certData, $result);
		$this->validateAuthorityKeyIdentifier($certData, $chain, $result);
		$this->validateSubjectKeyIdentifier($certData, $result);

		return $result;
	}

	private function validateBasicConstraints(array $certData, array &$result): void {
		if (!isset($certData['extensions']['basicConstraints'])) {
			$result['errors'][] = 'Missing basicConstraints extension';
			$result['valid'] = false;
			return;
		}

		$isCA = $this->isRootCertificate(['subject' => $certData['subject'], 'issuer' => $certData['issuer']]);
		$basicConstraints = $certData['extensions']['basicConstraints'];
		
		if ($isCA && !str_contains($basicConstraints, 'CA:TRUE')) {
			$result['errors'][] = 'CA certificate missing CA:TRUE in basicConstraints';
			$result['valid'] = false;
		} elseif (!$isCA && !str_contains($basicConstraints, 'CA:FALSE')) {
			$result['errors'][] = 'End-entity certificate missing CA:FALSE in basicConstraints';
			$result['valid'] = false;
		}
		
		$result['extensions']['basicConstraints'] = $basicConstraints;
	}

	private function validateKeyUsage(array $certData, array &$result): void {
		if (!isset($certData['extensions']['keyUsage'])) {
			$result['errors'][] = 'Missing keyUsage extension';
			$result['valid'] = false;
			return;
		}

		$keyUsage = $certData['extensions']['keyUsage'];
		$isCA = $this->isRootCertificate(['subject' => $certData['subject'], 'issuer' => $certData['issuer']]);
		
		if ($isCA) {
			if (!str_contains($keyUsage, 'Certificate Sign')) {
				$result['errors'][] = 'CA certificate missing Certificate Sign in keyUsage';
				$result['valid'] = false;
			}
		} else {
			$requiredUsages = ['Digital Signature', 'Key Encipherment', 'Non Repudiation'];
			foreach ($requiredUsages as $usage) {
				if (!str_contains($keyUsage, $usage)) {
					$result['errors'][] = "End-entity certificate missing {$usage} in keyUsage";
					$result['valid'] = false;
				}
			}
		}
		
		$result['extensions']['keyUsage'] = $keyUsage;
	}

	private function validateAuthorityKeyIdentifier(array $certData, array $chain, array &$result): void {
		$isRoot = $this->isRootCertificate(['subject' => $certData['subject'], 'issuer' => $certData['issuer']]);
		
		if (!isset($certData['extensions']['authorityKeyIdentifier'])) {
			if (!$isRoot) {
				$result['errors'][] = 'Non-root certificate missing authorityKeyIdentifier';
				$result['valid'] = false;
			}
			return;
		}

		$aki = $certData['extensions']['authorityKeyIdentifier'];
		$result['extensions']['authorityKeyIdentifier'] = $aki;
		
		if (!$isRoot) {
			if (!str_contains($aki, 'keyid:')) {
				$result['errors'][] = 'authorityKeyIdentifier missing keyid for non-root certificate';
				$result['valid'] = false;
			}
			
			$this->validateAkiMatchesIssuerSki($certData, $chain, $result);
		}
	}

	private function validateSubjectKeyIdentifier(array $certData, array &$result): void {
		if (!isset($certData['extensions']['subjectKeyIdentifier'])) {
			$result['errors'][] = 'Missing subjectKeyIdentifier extension';
			$result['valid'] = false;
			return;
		}
		
		$ski = $certData['extensions']['subjectKeyIdentifier'];
		$result['extensions']['subjectKeyIdentifier'] = $ski;
		
		if (!preg_match('/^[0-9A-F:]+$/', $ski)) {
			$result['errors'][] = 'Invalid subjectKeyIdentifier format';
			$result['valid'] = false;
		}
	}

	private function validateAkiMatchesIssuerSki(array $certData, array $chain, array &$result): void {
		$issuerDN = $this->normalizeDistinguishedName((array)$certData['issuer']);
		
		foreach ($chain as $chainCert) {
			if (!isset($chainCert['name'])) {
				continue;
			}
			
			$chainCertData = openssl_x509_parse(file_get_contents($chainCert['name']));
			if (!$chainCertData) {
				continue;
			}
			
			$chainSubjectDN = $this->normalizeDistinguishedName((array)$chainCertData['subject']);
			
			if ($issuerDN === $chainSubjectDN) {
				if (isset($chainCertData['extensions']['subjectKeyIdentifier'])) {
					$issuerSki = $chainCertData['extensions']['subjectKeyIdentifier'];
					$aki = $certData['extensions']['authorityKeyIdentifier'];
					
					if (!str_contains($aki, $issuerSki)) {
						$result['errors'][] = 'authorityKeyIdentifier does not match issuer subjectKeyIdentifier';
						$result['valid'] = false;
					}
				}
				break;
			}
		}
	}
}
