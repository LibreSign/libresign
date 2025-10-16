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
}
